<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

const REVIEW_LOGIN = 'apple-review@koraexpress.app';
const REVIEW_SIGNUP = 'apple-review+signup@koraexpress.app';
const REVIEW_CODE = '654321';

beforeEach(function (): void {
    Notification::fake();
    $this->seed(KoraBusRolePermissionSeeder::class);

    config([
        'services.store_review.enabled' => true,
        'services.store_review.otp_code' => REVIEW_CODE,
        'services.store_review.identifiers' => [REVIEW_LOGIN, REVIEW_SIGNUP],
        'services.store_review.login_identifier' => REVIEW_LOGIN,
        'services.store_review.pin' => '2468',
    ]);
});

function reviewUser(?string $phone = null): User
{
    return User::factory()->create([
        'email' => REVIEW_LOGIN,
        'phone' => $phone,
        'pin_hash' => Hash::make('2468'),
    ]);
}

function loginPayload(string $identifier, array $overrides = []): array
{
    return array_merge([
        'identifier' => $identifier,
        'pin' => '2468',
        'device_name' => 'App Review iPhone',
        'platform' => 'ios',
        'device_fingerprint' => 'app-review-device-fingerprint',
    ], $overrides);
}

it('accepts the store review code on a new device login without sending a notification', function (): void {
    Log::spy();
    reviewUser();

    $login = $this->postJson('/api/v1/auth/login', loginPayload(REVIEW_LOGIN))
        ->assertAccepted()
        ->assertJsonPath('data.requires_otp', true)
        ->assertJsonPath('data.purpose', 'login_new_device');

    $challengeUuid = $login->json('data.otp_challenge_uuid');

    Notification::assertCount(0);

    $verification = $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $challengeUuid,
        'purpose' => 'login_new_device',
        'code' => REVIEW_CODE,
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['verification_token', 'verified_at']]);

    $this->postJson('/api/v1/auth/login', loginPayload(REVIEW_LOGIN, [
        'otp_challenge_uuid' => $challengeUuid,
        'verification_token' => $verification->json('data.verification_token'),
    ]))
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user', 'device']]);

    expect(User::query()->where('email', REVIEW_LOGIN)->sole()->devices()->count())->toBe(1);
    Notification::assertCount(0);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context): bool => $message === 'store_review.otp_code_accepted'
            && $context['otp_challenge_uuid'] === $challengeUuid
            && $context['purpose'] === 'login_new_device')
        ->once();
});

it('allows the reviewer to register the second whitelisted account with the fixed code', function (): void {
    $request = $this->postJson('/api/v1/auth/otp/request', [
        'identifier' => REVIEW_SIGNUP,
        'purpose' => 'registration',
    ])->assertAccepted();

    $challengeUuid = $request->json('data.otp_challenge_uuid');

    Notification::assertCount(0);

    $verification = $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $challengeUuid,
        'purpose' => 'registration',
        'code' => REVIEW_CODE,
    ])->assertOk();

    $this->postJson('/api/v1/auth/register/complete', [
        'otp_challenge_uuid' => $challengeUuid,
        'verification_token' => $verification->json('data.verification_token'),
        'pin' => '1357',
        'pin_confirmation' => '1357',
        'name' => 'App Reviewer',
        'device_name' => 'App Review iPhone',
        'platform' => 'ios',
        'device_fingerprint' => 'app-review-signup-fingerprint',
    ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['token', 'user']]);

    $user = User::query()->where('email', REVIEW_SIGNUP)->sole();

    expect($user->hasRole('passenger'))->toBeTrue()
        ->and($user->status)->toBe(UserStatus::Active)
        ->and(Hash::check('1357', (string) $user->pin_hash))->toBeTrue();

    Notification::assertCount(0);
});

it('rejects the fixed code for an identifier outside the whitelist', function (): void {
    $challenge = pendingOtpChallenge('outsider@example.com', OtpPurpose::Registration, '111111');

    $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $challenge->uuid,
        'purpose' => 'registration',
        'code' => REVIEW_CODE,
    ])->assertUnprocessable()->assertJsonValidationErrors('code');

    expect($challenge->refresh()->status)->toBe(OtpChallengeStatus::Pending);
});

it('rejects the fixed code when the store review gate is disabled', function (): void {
    config(['services.store_review.enabled' => false]);

    $challenge = pendingOtpChallenge(REVIEW_LOGIN, OtpPurpose::Registration, '111111');

    $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $challenge->uuid,
        'purpose' => 'registration',
        'code' => REVIEW_CODE,
    ])->assertUnprocessable()->assertJsonValidationErrors('code');

    $this->postJson('/api/v1/auth/otp/request', [
        'identifier' => REVIEW_SIGNUP,
        'purpose' => 'registration',
    ])->assertAccepted();

    Notification::assertCount(1);
});

it('rejects a malformed fixed code configured in the environment', function (string $code): void {
    config(['services.store_review.otp_code' => $code]);

    $challenge = pendingOtpChallenge(REVIEW_LOGIN, OtpPurpose::Registration, '111111');

    $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $challenge->uuid,
        'purpose' => 'registration',
        'code' => REVIEW_CODE,
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
})->with([
    'empty' => '',
    'not numeric' => 'abcdef',
    'too short' => '12345',
    'too long' => '1234567',
]);

it('keeps the expiration and attempt guards for the fixed code', function (): void {
    $expired = pendingOtpChallenge(REVIEW_LOGIN, OtpPurpose::Registration, '111111', now()->subMinute());
    $exhausted = pendingOtpChallenge(REVIEW_LOGIN, OtpPurpose::Registration, '111111');
    $exhausted->forceFill(['attempts' => 5])->save();

    foreach ([$expired, $exhausted] as $challenge) {
        $this->postJson('/api/v1/auth/otp/verify', [
            'otp_challenge_uuid' => $challenge->uuid,
            'purpose' => 'registration',
            'code' => REVIEW_CODE,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }
});

it('accepts the fixed code when the review account also has a phone number', function (): void {
    reviewUser('+2290197421860');

    $login = $this->postJson('/api/v1/auth/login', loginPayload('+2290197421860'))
        ->assertAccepted()
        ->assertJsonPath('data.requires_otp', true);

    $challenge = AuthOtpChallenge::query()->sole();
    expect($challenge->identifier)->toBe('+2290197421860');

    Notification::assertCount(0);

    $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $login->json('data.otp_challenge_uuid'),
        'purpose' => 'login_new_device',
        'code' => REVIEW_CODE,
    ])->assertOk();
});

it('accepts the fixed code for pin reset and account unlock', function (string $purpose): void {
    reviewUser();

    $request = $this->postJson('/api/v1/auth/otp/request', [
        'identifier' => REVIEW_LOGIN,
        'purpose' => $purpose,
    ])->assertAccepted();

    Notification::assertCount(0);

    $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $request->json('data.otp_challenge_uuid'),
        'purpose' => $purpose,
        'code' => REVIEW_CODE,
    ])->assertOk();
})->with(['pin_reset', 'account_unlock']);

it('still accepts the real one-time code when the gate is enabled', function (): void {
    $challenge = pendingOtpChallenge(REVIEW_LOGIN, OtpPurpose::Registration, '222333');

    $this->postJson('/api/v1/auth/otp/verify', [
        'otp_challenge_uuid' => $challenge->uuid,
        'purpose' => 'registration',
        'code' => '222333',
    ])->assertOk();
});
