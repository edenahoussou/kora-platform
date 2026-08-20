<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\UserSocialAccount;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\User as SocialiteUser;

function mockSocialiteUser(string $provider, string $providerId, ?string $email = null, ?string $name = null, ?string $avatar = null, ?string $nickname = null): void
{
    $socialiteUser = (new SocialiteUser)
        ->map([
            'id' => $providerId,
            'nickname' => $nickname,
            'name' => $name,
            'email' => $email,
            'avatar' => $avatar,
            'avatar_original' => $avatar,
        ])
        ->setToken('fake-provider-token')
        ->setRefreshToken('fake-refresh-token')
        ->setExpiresIn(3600);

    $mock = Mockery::mock(SocialiteFactory::class);
    $mock->shouldReceive('driver')
        ->with($provider)
        ->andReturnSelf();
    $mock->shouldReceive('userFromToken')
        ->with('fake-provider-token')
        ->andReturn($socialiteUser);

    app()->instance(SocialiteFactory::class, $mock);
}

function oauthPayload(array $overrides = []): array
{
    return array_merge([
        'provider' => 'google',
        'provider_token' => 'fake-provider-token',
        'device_name' => 'iPhone 15 Pro',
        'platform' => 'ios',
        'device_fingerprint' => str_repeat('a', 24),
    ], $overrides);
}

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

it('creates a new user on first oauth login and returns a sanctum token', function (): void {
    mockSocialiteUser('google', 'google-123', 'aicha.hounkpe@gmail.com', 'Aïcha Hounkpè');

    $this->postJson('/api/v1/auth/oauth/token', oauthPayload())
        ->assertCreated()
        ->assertJsonStructure([
            'data' => ['token', 'token_type', 'expires_at', 'user' => ['uuid', 'name', 'email', 'avatar_url', 'status', 'permissions'], 'device' => ['uuid', 'name', 'platform']],
        ])
        ->assertJsonPath('data.user.email', 'aicha.hounkpe@gmail.com')
        ->assertJsonPath('data.user.name', 'Aïcha Hounkpè')
        ->assertJsonPath('data.user.status', 'active');

    $user = User::query()->where('email', 'aicha.hounkpe@gmail.com')->firstOrFail();

    expect($user->hasRole('passenger'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();

    $account = UserSocialAccount::query()
        ->where('provider', 'google')
        ->where('provider_user_id', 'google-123')
        ->firstOrFail();

    expect($account->user_id)->toBe($user->id)
        ->and($account->name)->toBe('Aïcha Hounkpè');
});

it('logs in an existing user via a linked social account', function (): void {
    $user = User::factory()->create([
        'email' => 'serge@example.com',
        'status' => UserStatus::Active,
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('passenger');

    UserSocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'facebook',
        'provider_user_id' => 'fb-456',
        'name' => 'Serge Adjibade',
    ]);

    mockSocialiteUser('facebook', 'fb-456', 'serge@example.com', 'Serge Adjibade');

    $this->postJson('/api/v1/auth/oauth/token', oauthPayload([
        'provider' => 'facebook',
    ]))
        ->assertOk()
        ->assertJsonPath('data.user.uuid', $user->uuid);

    // Aucun doublon de compte social
    expect(UserSocialAccount::query()->where('provider', 'facebook')->where('provider_user_id', 'fb-456')->count())->toBe(1);
});

it('links a social account to an existing user with the same email', function (): void {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'status' => UserStatus::Active,
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('passenger');

    expect(UserSocialAccount::query()->count())->toBe(0);

    mockSocialiteUser('google', 'google-789', 'existing@example.com', 'Existing User');

    $this->postJson('/api/v1/auth/oauth/token', oauthPayload())
        ->assertOk()
        ->assertJsonPath('data.user.uuid', $user->uuid);

    // Le compte social a été lié à l'utilisateur existant
    $account = UserSocialAccount::query()
        ->where('provider', 'google')
        ->where('provider_user_id', 'google-789')
        ->firstOrFail();

    expect($account->user_id)->toBe($user->id);
    expect(User::query()->where('email', 'existing@example.com')->count())->toBe(1);
});

it('unlocks a locked user account on oauth login', function (): void {
    $user = User::factory()->create([
        'email' => 'locked@example.com',
        'status' => UserStatus::Locked,
        'locked_until' => now()->addMinutes(30),
        'failed_pin_attempts' => 5,
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('passenger');

    UserSocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-locked',
        'name' => 'Locked User',
    ]);

    mockSocialiteUser('google', 'google-locked', 'locked@example.com', 'Locked User');

    $this->postJson('/api/v1/auth/oauth/token', oauthPayload())
        ->assertOk();

    $user->refresh();

    expect($user->status)->toBe(UserStatus::Active)
        ->and($user->locked_until)->toBeNull()
        ->and($user->failed_pin_attempts)->toBe(0);
});

it('rejects a suspended user on oauth login', function (): void {
    $user = User::factory()->create([
        'email' => 'suspended@example.com',
        'status' => UserStatus::Suspended,
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('passenger');

    UserSocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-suspended',
        'name' => 'Suspended User',
    ]);

    mockSocialiteUser('google', 'google-suspended', 'suspended@example.com', 'Suspended User');

    $this->postJson('/api/v1/auth/oauth/token', oauthPayload())
        ->assertForbidden()
        ->assertJsonPath('error.code', 'account_suspended');
});

it('rejects an invalid oauth token', function (): void {
    $mock = Mockery::mock(SocialiteFactory::class);
    $mock->shouldReceive('driver')->with('google')->andReturnSelf();
    $mock->shouldReceive('userFromToken')->andThrow(new Exception('Invalid token'));
    app()->instance(SocialiteFactory::class, $mock);

    $this->postJson('/api/v1/auth/oauth/token', oauthPayload())
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'invalid_oauth_token');
});

it('validates required fields and provider enum', function (string $field, mixed $value): void {
    $this->postJson('/api/v1/auth/oauth/token', oauthPayload([$field => $value]))
        ->assertJsonValidationErrorFor($field);
})->with([
    'provider missing' => ['provider', null],
    'provider invalid' => ['provider', 'twitter'],
    'provider_token missing' => ['provider_token', null],
    'device_name missing' => ['device_name', null],
    'platform invalid' => ['platform', 'windows'],
    'device_fingerprint too short' => ['device_fingerprint', 'short'],
]);

it('creates a user with a synthetic email when provider returns no email', function (): void {
    mockSocialiteUser('tiktok', 'tiktok-001', null, null, null, 'dancer123');

    $this->postJson('/api/v1/auth/oauth/token', oauthPayload([
        'provider' => 'tiktok',
    ]))
        ->assertCreated();

    $user = User::query()->where('email', 'tiktok.tiktok-001@social.korabus.app')->firstOrFail();

    expect($user->name)->toBe('dancer123');
});

it('registers a trusted device on oauth login', function (): void {
    mockSocialiteUser('google', 'google-device', 'device@example.com', 'Device User');

    $this->postJson('/api/v1/auth/oauth/token', oauthPayload([
        'device_fingerprint' => str_repeat('b', 24),
    ]))
        ->assertCreated();

    $user = User::query()->where('email', 'device@example.com')->firstOrFail();

    expect($user->devices)->toHaveCount(1)
        ->and($user->devices->first()->trusted_at)->not->toBeNull();
});
