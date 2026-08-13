<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Notifications\IdentityAccess\OtpCodeNotification;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

it('requests an email otp without storing the clear code', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/otp/request', [
        'email' => 'aicha@example.com',
        'purpose' => 'registration',
    ])
        ->assertAccepted()
        ->assertJsonPath('data.purpose', 'registration')
        ->assertJsonStructure(['data' => ['otp_challenge_uuid', 'email', 'expires_at']]);

    $challenge = AuthOtpChallenge::query()->firstOrFail();

    expect($challenge->email)->toBe('aicha@example.com')
        ->and($challenge->code_hash)->not->toBe('123456')
        ->and($challenge->status)->toBe(OtpChallengeStatus::Pending);

    Notification::assertSentOnDemand(OtpCodeNotification::class);
});

it('verifies a valid otp and returns a one-time verification token', function (): void {
    $challenge = pendingOtpChallenge('aicha@example.com', OtpPurpose::Registration, '123456');

    $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $challenge->uuid,
        'purpose' => 'registration',
        'code' => '123456',
    ])
        ->assertOk()
        ->assertJsonPath('data.otp_challenge_uuid', $challenge->uuid)
        ->assertJsonPath('data.purpose', 'registration')
        ->assertJsonStructure(['data' => ['verification_token', 'verified_at']]);

    $challenge->refresh();

    expect($challenge->status)->toBe(OtpChallengeStatus::Verified)
        ->and($challenge->verification_token_hash)->not->toBeNull();
});

it('rejects invalid or expired otp codes', function (): void {
    $invalid = pendingOtpChallenge('aicha@example.com', OtpPurpose::Registration, '123456');
    $expired = pendingOtpChallenge('serge@example.com', OtpPurpose::Registration, '222222', expiresAt: now()->subMinute());

    $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $invalid->uuid,
        'purpose' => 'registration',
        'code' => '000000',
    ])->assertUnprocessable();

    $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $expired->uuid,
        'purpose' => 'registration',
        'code' => '222222',
    ])->assertUnprocessable();

    expect($expired->refresh()->status)->toBe(OtpChallengeStatus::Expired);
});

it('completes registration with a pin, passenger role, trusted device and seven-day token', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    [$challenge, $verificationToken] = verifiedOtpChallenge('aicha@example.com', OtpPurpose::Registration);

    $response = $this->postJson('/api/v1/auth/register/complete', [
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $verificationToken,
        'pin' => '1234',
        'pin_confirmation' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'device-fingerprint-registration',
        'push_token' => 'push-token-1',
    ])
        ->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'aicha@example.com')
        ->assertJsonPath('data.device.name', 'Tecno Spark 20')
        ->assertJsonMissingPath('data.user.id')
        ->assertJsonMissingPath('data.user.pin_hash');

    $user = User::query()->where('email', 'aicha@example.com')->firstOrFail();

    expect($user->hasRole('passenger'))->toBeTrue()
        ->and(Hash::check('1234', $user->pin_hash))->toBeTrue()
        ->and($user->tokens()->firstOrFail()->expires_at->greaterThan(now()->addDays(6)))->toBeTrue()
        ->and($response->json('data.user.permissions'))->toContain('bookings.create');
});

it('logs in with a known trusted device using pin', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $user = pinUser('known@example.com');
    trustedDevice($user, 'known-device-fingerprint');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'known@example.com',
        'pin' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'known-device-fingerprint',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.uuid', $user->uuid)
        ->assertJsonStructure(['data' => ['token', 'expires_at', 'user', 'device']]);
});

it('requires otp when logging in from a new device', function (): void {
    Notification::fake();
    pinUser('new-device@example.com');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'new-device@example.com',
        'pin' => '1234',
        'device_name' => 'Infinix Note',
        'platform' => 'android',
        'device_fingerprint' => 'brand-new-device-fingerprint',
    ])
        ->assertAccepted()
        ->assertJsonPath('data.requires_otp', true)
        ->assertJsonPath('data.purpose', 'login_new_device')
        ->assertJsonStructure(['data' => ['otp_challenge_uuid', 'expires_at']]);

    Notification::assertSentTo(User::query()->where('email', 'new-device@example.com')->firstOrFail(), OtpCodeNotification::class);
});

it('logs in from a new device after otp verification', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $user = pinUser('verified-device@example.com');
    [$challenge, $verificationToken] = verifiedOtpChallenge('verified-device@example.com', OtpPurpose::LoginNewDevice, $user);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'verified-device@example.com',
        'pin' => '1234',
        'device_name' => 'Infinix Note',
        'platform' => 'android',
        'device_fingerprint' => 'verified-new-device',
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $verificationToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.device.name', 'Infinix Note');

    expect(UserDevice::query()->whereBelongsTo($user)->count())->toBe(1);
});

it('protects me and devices routes with sanctum tokens', function (): void {
    $user = pinUser('protected@example.com');
    $device = trustedDevice($user, 'protected-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.uuid', $user->uuid)
        ->assertJsonMissingPath('data.user.id')
        ->assertJsonMissingPath('data.user.pin_hash');

    $this->withToken($token)
        ->getJson('/api/v1/auth/devices')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $device->uuid)
        ->assertJsonMissingPath('data.0.id');
});

it('revokes only devices owned by the authenticated user', function (): void {
    $owner = pinUser('owner@example.com');
    $other = pinUser('other@example.com');
    $ownerDevice = trustedDevice($owner, 'owner-device');
    $otherDevice = trustedDevice($other, 'other-device');
    $token = $owner->createToken("device:{$ownerDevice->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/v1/auth/devices/{$otherDevice->uuid}")
        ->assertNotFound();

    $this->withToken($token)
        ->deleteJson("/api/v1/auth/devices/{$ownerDevice->uuid}")
        ->assertNoContent();

    expect($ownerDevice->refresh()->status)->toBe(UserDeviceStatus::Revoked)
        ->and($otherDevice->refresh()->status)->toBe(UserDeviceStatus::Active);
});

it('logs out by revoking the current access token', function (): void {
    $user = pinUser('logout@example.com');
    $device = trustedDevice($user, 'logout-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7));

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('resets pin after a verified email otp', function (): void {
    $user = pinUser('reset@example.com');
    [$challenge, $verificationToken] = verifiedOtpChallenge('reset@example.com', OtpPurpose::PinReset, $user);

    $this->postJson('/api/v1/auth/pin/reset', [
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $verificationToken,
        'pin' => '9876',
        'pin_confirmation' => '9876',
    ])->assertOk();

    expect(Hash::check('9876', $user->refresh()->pin_hash))->toBeTrue();
});

it('rate limits otp requests', function (): void {
    Notification::fake();

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'limit@example.com',
            'purpose' => 'registration',
        ])->assertAccepted();
    }

    $this->postJson('/api/v1/auth/otp/request', [
        'email' => 'limit@example.com',
        'purpose' => 'registration',
    ])->assertTooManyRequests();
});

function pendingOtpChallenge(
    string $email,
    OtpPurpose $purpose,
    string $code,
    mixed $expiresAt = null,
    ?User $user = null,
): AuthOtpChallenge {
    return AuthOtpChallenge::query()->create([
        'email' => $email,
        'user_id' => $user?->id,
        'purpose' => $purpose,
        'code_hash' => Hash::make($code),
        'attempts' => 0,
        'expires_at' => $expiresAt ?: now()->addMinutes(5),
        'status' => OtpChallengeStatus::Pending,
    ]);
}

/**
 * @return array{0: AuthOtpChallenge, 1: string}
 */
function verifiedOtpChallenge(string $email, OtpPurpose $purpose, ?User $user = null): array
{
    $verificationToken = Str::random(64);
    $challenge = AuthOtpChallenge::query()->create([
        'email' => $email,
        'user_id' => $user?->id,
        'purpose' => $purpose,
        'code_hash' => Hash::make('123456'),
        'verification_token_hash' => hash('sha256', $verificationToken),
        'attempts' => 1,
        'expires_at' => now()->addMinutes(5),
        'verified_at' => now(),
        'status' => OtpChallengeStatus::Verified,
    ]);

    return [$challenge, $verificationToken];
}

function pinUser(string $email): User
{
    return User::factory()->create([
        'email' => $email,
        'pin_hash' => Hash::make('1234'),
    ]);
}

function trustedDevice(User $user, string $fingerprint): UserDevice
{
    return UserDevice::query()->create([
        'user_id' => $user->id,
        'name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint_hash' => hash('sha256', $fingerprint),
        'trusted_at' => now(),
        'last_seen_at' => now(),
        'status' => UserDeviceStatus::Active,
    ]);
}
