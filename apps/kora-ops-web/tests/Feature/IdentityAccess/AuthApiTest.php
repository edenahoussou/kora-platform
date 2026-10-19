<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Data\UserStatus;
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

    expect($challenge->identifier)->toBe('aicha@example.com')
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

it('logs in with the identifier email field', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $user = pinUser('identifier@example.com');
    trustedDevice($user, 'identifier-device-fingerprint');

    $this->postJson('/api/v1/auth/login', [
        'identifier' => 'IDENTIFIER@example.com',
        'pin' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'identifier-device-fingerprint',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'identifier@example.com');
});

it('logs in with an international or local phone identifier', function (string $identifier): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $user = pinUser('phone-login@example.com', '+2290197421860');
    trustedDevice($user, 'phone-login-device-fingerprint');

    $this->postJson('/api/v1/auth/login', [
        'identifier' => $identifier,
        'pin' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'phone-login-device-fingerprint',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.uuid', $user->uuid);
})->with([
    '+2290197421860',
    '01 97 42 18 60',
]);

it('rejects an ambiguous login identifier request', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'identifier' => 'aicha@example.com',
        'email' => 'legacy@example.com',
        'pin' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'ambiguous-device-fingerprint',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('identifier');
});

it('rejects a missing or malformed login identifier', function (): void {
    $basePayload = [
        'pin' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'invalid-identifier-fingerprint',
    ];

    $this->postJson('/api/v1/auth/login', $basePayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['identifier', 'email']);

    $this->postJson('/api/v1/auth/login', $basePayload + ['identifier' => 'not-an-email-or-phone'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('identifier');
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

it('issues a new-device otp to WhatsApp for a phone login', function (): void {
    Notification::fake();
    $user = pinUser('phone-new-device@example.com', '+2290197421860');

    $response = $this->postJson('/api/v1/auth/login', [
        'identifier' => '01 97 42 18 60',
        'pin' => '1234',
        'device_name' => 'Infinix Note',
        'platform' => 'android',
        'device_fingerprint' => 'phone-new-device-fingerprint',
    ])->assertAccepted();

    $challenge = AuthOtpChallenge::query()->firstOrFail();

    expect($response->json('data.otp_challenge_uuid'))->toBe($challenge->uuid)
        ->and($challenge->identifier)->toBe('+2290197421860')
        ->and($challenge->user_id)->toBe($user->id);

    Notification::assertSentTo($user, OtpCodeNotification::class);
});

it('requests a WhatsApp otp for a phone registration', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/otp/request', [
        'identifier' => '+2290153234060',
        'purpose' => 'registration',
    ])
        ->assertAccepted()
        ->assertJsonPath('data.purpose', 'registration')
        ->assertJsonPath('data.identifier', '+2290153234060')
        ->assertJsonPath('data.email', null);

    $challenge = AuthOtpChallenge::query()->firstOrFail();

    expect($challenge->identifier)->toBe('+2290153234060')
        ->and($challenge->user_id)->toBeNull()
        ->and($challenge->status)->toBe(OtpChallengeStatus::Pending);

    Notification::assertSentOnDemand(OtpCodeNotification::class);
});

it('requests an otp by phone for an existing account', function (): void {
    Notification::fake();
    $user = pinUser('phone-otp@example.com', '+2290197421860');

    $this->postJson('/api/v1/auth/otp/request', [
        'identifier' => '01 97 42 18 60',
        'purpose' => 'pin_reset',
    ])
        ->assertAccepted()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.purpose', 'pin_reset');

    expect(AuthOtpChallenge::query()->firstOrFail()->user_id)->toBe($user->id);
    Notification::assertSentTo($user, OtpCodeNotification::class);
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

it('shares the otp rate limit across equivalent phone formats', function (): void {
    Notification::fake();
    pinUser('normalized-rate-limit@example.com', '+2290197421860');

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/auth/otp/request', [
            'identifier' => $i === 0 ? '+2290197421860' : '01 97 42 18 60',
            'purpose' => 'pin_reset',
        ])->assertAccepted();
    }

    $this->postJson('/api/v1/auth/otp/request', [
        'identifier' => '+2290197421860',
        'purpose' => 'pin_reset',
    ])->assertTooManyRequests();
});

it('locks account after 5 failed pin attempts', function (): void {
    Notification::fake();
    $user = pinUser('lockout@example.com');
    trustedDevice($user, 'lockout-device-fingerprint');

    for ($i = 0; $i < 4; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'lockout@example.com',
            'pin' => '0000',
            'device_name' => 'Tecno Spark 20',
            'platform' => 'android',
            'device_fingerprint' => 'lockout-device-fingerprint',
        ])->assertJsonPath('error.code', 'invalid_credentials');
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'lockout@example.com',
        'pin' => '0000',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'lockout-device-fingerprint',
    ])
        ->assertStatus(423)
        ->assertJsonPath('error.code', 'account_locked')
        ->assertJsonStructure(['error' => ['code', 'message', 'locked_until']]);

    expect($user->refresh()->status)->toBe(UserStatus::Locked)
        ->and($user->refresh()->locked_until)->not->toBeNull();
});

it('returns 423 when logging into a locked account', function (): void {
    $user = pinUser('locked@example.com');
    trustedDevice($user, 'locked-device-fingerprint');
    $user->forceFill([
        'status' => UserStatus::Locked,
        'locked_until' => now()->addMinutes(30),
    ])->save();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'locked@example.com',
        'pin' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'locked-device-fingerprint',
    ])
        ->assertStatus(423)
        ->assertJsonPath('error.code', 'account_locked');
});

it('unlocks account after verified otp', function (): void {
    $user = pinUser('unlock@example.com');
    $user->forceFill([
        'status' => UserStatus::Locked,
        'locked_until' => now()->addMinutes(30),
        'failed_pin_attempts' => 5,
    ])->save();

    [$challenge, $verificationToken] = verifiedOtpChallenge('unlock@example.com', OtpPurpose::AccountUnlock, $user);

    $this->postJson('/api/v1/auth/unlock', [
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $verificationToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'unlock@example.com');

    expect($user->refresh()->status)->toBe(UserStatus::Active)
        ->and($user->refresh()->failed_pin_attempts)->toBe(0)
        ->and($user->refresh()->locked_until)->toBeNull();
});

it('updates profile with name, phone and birth_date', function (): void {
    $user = pinUser('profile@example.com');
    $device = trustedDevice($user, 'profile-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/auth/me', [
            'name' => 'Aïcha HOUNKPÈ',
            'email' => 'profile@example.com',
            'phone' => '+229 01 97 42 18 60',
            'country_code' => '229',
            'birth_date' => '1994-04-18',
        ])
        ->assertOk()
        ->assertJsonPath('data.user.name', 'Aïcha HOUNKPÈ')
        ->assertJsonPath('data.user.phone', '+2290197421860')
        ->assertJsonPath('data.user.birth_date', '1994-04-18T00:00:00.000000Z');
});

it('requires email re-verification when email changes', function (): void {
    $user = pinUser('email-change@example.com');
    $device = trustedDevice($user, 'email-change-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/auth/me', [
            'name' => $user->name,
            'email' => 'new-email@example.com',
        ])
        ->assertOk();

    expect($user->refresh()->email)->toBe('new-email@example.com')
        ->and($user->refresh()->email_verified_at)->toBeNull();
});

it('changes pin with current pin verification', function (): void {
    $user = pinUser('changepin@example.com');
    $device = trustedDevice($user, 'changepin-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/pin/change', [
            'current_pin' => '1234',
            'new_pin' => '5678',
            'new_pin_confirmation' => '5678',
        ])
        ->assertOk();

    expect(Hash::check('5678', $user->refresh()->pin_hash))->toBeTrue();
});

it('rejects pin change with wrong current pin', function (): void {
    $user = pinUser('wrongpin@example.com');
    $device = trustedDevice($user, 'wrongpin-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/pin/change', [
            'current_pin' => '9999',
            'new_pin' => '5678',
            'new_pin_confirmation' => '5678',
        ])
        ->assertUnprocessable();
});

it('rejects same pin as new pin', function (): void {
    $user = pinUser('samepin@example.com');
    $device = trustedDevice($user, 'samepin-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/pin/change', [
            'current_pin' => '1234',
            'new_pin' => '1234',
            'new_pin_confirmation' => '1234',
        ])
        ->assertUnprocessable();
});

it('invalidates previous pending otp when requesting new one', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/otp/request', [
        'email' => 'invalidate@example.com',
        'purpose' => 'registration',
    ])->assertAccepted();

    $firstChallenge = AuthOtpChallenge::query()->firstOrFail();
    expect($firstChallenge->status)->toBe(OtpChallengeStatus::Pending);

    $this->postJson('/api/v1/auth/otp/request', [
        'email' => 'invalidate@example.com',
        'purpose' => 'registration',
    ])->assertAccepted();

    expect($firstChallenge->refresh()->status)->toBe(OtpChallengeStatus::Expired);
});

it('stores and retrieves user preferences', function (): void {
    $user = pinUser('prefs@example.com');
    $device = trustedDevice($user, 'prefs-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/auth/preferences')
        ->assertOk()
        ->assertJsonPath('data.push_notifications', false)
        ->assertJsonPath('data.sms_notifications', true)
        ->assertJsonPath('data.locale', 'fr');
});

it('updates the sms notification preference', function (): void {
    $user = pinUser('prefs-sms@example.com');
    $device = trustedDevice($user, 'prefs-sms-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/auth/preferences', ['sms_notifications' => false])
        ->assertOk()
        ->assertJsonPath('data.sms_notifications', false);
});

it('updates user preferences', function (): void {
    $user = pinUser('prefs-update@example.com');
    $device = trustedDevice($user, 'prefs-update-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/auth/preferences', [
            'push_notifications' => false,
            'locale' => 'en',
            'theme' => 'dark',
        ])
        ->assertOk()
        ->assertJsonPath('data.push_notifications', false)
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.theme', 'dark');
});

it('completes a phone registration and logs in by phone', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    [$challenge, $verificationToken] = verifiedOtpChallenge('+2290153234060', OtpPurpose::Registration);

    $this->postJson('/api/v1/auth/register/complete', [
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $verificationToken,
        'name' => 'AHOUSSOU TONY',
        'pin' => '1234',
        'pin_confirmation' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'device-fingerprint-phone-registration',
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.name', 'AHOUSSOU TONY')
        ->assertJsonPath('data.user.phone', '+2290153234060');

    $user = User::query()->where('phone', '+2290153234060')->firstOrFail();

    expect($user->email)->toEndWith('@accounts.kora.invalid')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->hasRole('passenger'))->toBeTrue()
        ->and(Hash::check('1234', $user->pin_hash))->toBeTrue();

    // register/complete a déjà enregistré l'appareil comme de confiance :
    // le login sur le même fingerprint ne redemande pas d'OTP.
    $this->postJson('/api/v1/auth/login', [
        'identifier' => '01 53 23 40 60',
        'pin' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'device-fingerprint-phone-registration',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.uuid', $user->uuid);
});

it('requires a name when creating a phone-only account', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    [$challenge, $verificationToken] = verifiedOtpChallenge('+2290153234060', OtpPurpose::Registration);

    $this->postJson('/api/v1/auth/register/complete', [
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $verificationToken,
        'pin' => '1234',
        'pin_confirmation' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'device-fingerprint-name-required',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'name_required');
});

it('claims an existing pin-less phone account at registration', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);

    $existing = User::factory()->create([
        'name' => 'Tony',
        'email' => 'traveler+'.Str::uuid().'@accounts.kora.invalid',
        'phone' => '+2290153234060',
        'pin_hash' => null,
        'email_verified_at' => null,
    ]);

    [$challenge, $verificationToken] = verifiedOtpChallenge('+2290153234060', OtpPurpose::Registration);

    $this->postJson('/api/v1/auth/register/complete', [
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $verificationToken,
        'name' => 'AHOUSSOU TONY',
        'email' => 'tony.ahoussou@example.com',
        'pin' => '1234',
        'pin_confirmation' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'device-fingerprint-claim',
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.uuid', $existing->uuid);

    $existing->refresh();

    expect($existing->email)->toBe('tony.ahoussou@example.com')
        ->and($existing->name)->toBe('AHOUSSOU TONY')
        ->and($existing->email_verified_at)->toBeNull()
        ->and(Hash::check('1234', $existing->pin_hash))->toBeTrue()
        ->and(User::query()->where('phone', '+2290153234060')->count())->toBe(1);
});

it('rejects registration on an account that already has a pin', function (): void {
    pinUser('existing@example.com', '+2290153234060');
    [$challenge, $verificationToken] = verifiedOtpChallenge('+2290153234060', OtpPurpose::Registration);

    $this->postJson('/api/v1/auth/register/complete', [
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $verificationToken,
        'name' => 'AHOUSSOU TONY',
        'pin' => '1234',
        'pin_confirmation' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'device-fingerprint-account-exists',
    ])
        ->assertConflict()
        ->assertJsonPath('error.code', 'account_exists');
});

it('rejects attaching a phone owned by another account', function (): void {
    pinUser('phone-owner@example.com', '+2290153234060');
    [$challenge, $verificationToken] = verifiedOtpChallenge('new-email@example.com', OtpPurpose::Registration);

    $this->postJson('/api/v1/auth/register/complete', [
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $verificationToken,
        'phone' => '+229 01 53 23 40 60',
        'pin' => '1234',
        'pin_confirmation' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'device-fingerprint-phone-taken',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'phone_taken');
});

it('rejects attaching an email owned by another account', function (): void {
    pinUser('email-owner@example.com');
    [$challenge, $verificationToken] = verifiedOtpChallenge('+2290153234060', OtpPurpose::Registration);

    $this->postJson('/api/v1/auth/register/complete', [
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $verificationToken,
        'name' => 'AHOUSSOU TONY',
        'email' => 'email-owner@example.com',
        'pin' => '1234',
        'pin_confirmation' => '1234',
        'device_name' => 'Tecno Spark 20',
        'platform' => 'android',
        'device_fingerprint' => 'device-fingerprint-email-taken',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'email_taken');
});

it('returns otp_delivery_unavailable when no channel can deliver', function (): void {
    // Compte sans téléphone et sans email joignable (placeholder
    // `.invalid` des comptes créés via la réservation assistée) : aucun
    // canal ne peut délivrer le code — mieux vaut une erreur métier
    // qu'un code perdu dans le vide.
    $placeholder = 'traveler+'.Str::uuid().'@accounts.kora.invalid';
    pinUser($placeholder);

    $this->postJson('/api/v1/auth/otp/request', [
        'identifier' => $placeholder,
        'purpose' => 'pin_reset',
    ])
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'otp_delivery_unavailable');
});

it('rejects a phone already in use when updating the profile', function (): void {
    pinUser('first-phone@example.com', '+2290153234060');
    $user = pinUser('second-phone@example.com');
    $device = trustedDevice($user, 'second-phone-device');
    $token = $user->createToken("device:{$device->uuid}", ['*'], now()->addDays(7))->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/auth/me', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '01 53 23 40 60',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

function pendingOtpChallenge(
    string $email,
    OtpPurpose $purpose,
    string $code,
    mixed $expiresAt = null,
    ?User $user = null,
): AuthOtpChallenge {
    return AuthOtpChallenge::query()->create([
        'identifier' => $email,
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
        'identifier' => $email,
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

function pinUser(string $email, ?string $phone = null): User
{
    return User::factory()->create([
        'email' => $email,
        'phone' => $phone,
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
