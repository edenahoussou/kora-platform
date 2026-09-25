<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\ConsumeVerifiedOtpChallengeAction;
use App\Modules\IdentityAccess\Actions\FindTrustedDeviceAction;
use App\Modules\IdentityAccess\Actions\IssueDeviceTokenAction;
use App\Modules\IdentityAccess\Actions\IssueOtpChallengeAction;
use App\Modules\IdentityAccess\Actions\UpsertTrustedDeviceAction;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Exceptions\AuthLockedException;
use App\Modules\IdentityAccess\Exceptions\AuthSuspendedException;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\IdentityAccess\Models\UserDevice;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\enum_value;

class AuthSessionService
{
    public function __construct(
        private ConsumeVerifiedOtpChallengeAction $consumeVerifiedOtpChallenge,
        private FindTrustedDeviceAction $findTrustedDevice,
        private IssueDeviceTokenAction $issueDeviceToken,
        private IssueOtpChallengeAction $issueOtpChallenge,
        private UpsertTrustedDeviceAction $upsertTrustedDevice,
        private PassengerIdentityResolver $identityResolver,
        private AuthIdentifierResolver $identifierResolver,
    ) {}

    /**
     * @param  array{otp_challenge_uuid: string, verification_token: string, pin: string, device_name: string, platform: string, device_fingerprint: string, push_token?: string|null}  $input
     * @return array{user: User, device: UserDevice, token: string, token_expires_at: CarbonInterface|null}
     */
    public function completeRegistration(array $input): array
    {
        $challenge = $this->consumeVerifiedOtpChallenge->handle(
            $input['otp_challenge_uuid'],
            OtpPurpose::Registration,
            $input['verification_token'],
        );

        $user = User::query()->firstOrCreate(
            ['email' => $challenge->email],
            [
                'name' => $this->defaultNameFromEmail($challenge->email),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'password' => Str::password(32),
            ],
        );

        $user->forceFill([
            'pin_hash' => Hash::make($input['pin']),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'status' => UserStatus::Active,
        ])->save();

        if ($user->wasRecentlyCreated || $user->roles()->doesntExist()) {
            $user->assignRole('passenger');
        }

        return $this->issueSessionForDevice($user, $input);
    }

    /**
     * @param  array{identifier: string, pin: string, device_name: string, platform: string, device_fingerprint: string, push_token?: string|null, otp_challenge_uuid?: string|null, verification_token?: string|null}  $input
     * @return array{requires_otp?: bool, challenge?: AuthOtpChallenge, user?: User, device?: UserDevice, token?: string, token_expires_at?: CarbonInterface|null}
     *
     * @throws ValidationException With error_code meta: invalid_credentials, account_locked, account_suspended
     */
    public function login(array $input, Request $request): array
    {
        $user = $this->identifierResolver->findUser($input['identifier']);

        if (! $user) {
            throw ValidationException::withMessages(['identifier' => ['Identifiants invalides.']]);
        }

        $status = enum_value($user->status);

        if ($status === UserStatus::Locked->value || ($user->locked_until && $user->locked_until->isFuture())) {
            throw new AuthLockedException($user->locked_until);
        }

        if ($status === UserStatus::Suspended->value) {
            throw new AuthSuspendedException;
        }

        if (! $user->pin_hash || ! Hash::check($input['pin'], $user->pin_hash)) {
            $justLocked = $this->registerFailedPinAttempt($user);

            if ($justLocked) {
                throw new AuthLockedException($user->refresh()->locked_until);
            }

            throw ValidationException::withMessages(['identifier' => ['Identifiants invalides.']]);
        }

        $user->forceFill([
            'failed_pin_attempts' => 0,
            'locked_until' => null,
            'status' => UserStatus::Active,
        ])->save();

        $trustedDevice = $this->findTrustedDevice->handle($user, $input['device_fingerprint']);

        if (! $trustedDevice && ! ($input['otp_challenge_uuid'] ?? null)) {
            return [
                'requires_otp' => true,
                'challenge' => $this->issueOtpChallenge->handle($user, OtpPurpose::LoginNewDevice, $request),
            ];
        }

        if (! $trustedDevice) {
            $verificationToken = $input['verification_token'] ?? null;

            if (! is_string($verificationToken)) {
                throw ValidationException::withMessages([
                    'verification_token' => ['La vérification OTP est requise pour ce nouvel appareil.'],
                ]);
            }

            $this->consumeVerifiedOtpChallenge->handle(
                (string) $input['otp_challenge_uuid'],
                OtpPurpose::LoginNewDevice,
                $verificationToken,
            );
        }

        return $this->issueSessionForDevice($user, $input);
    }

    /**
     * @param  array{otp_challenge_uuid: string, verification_token: string}  $input
     */
    public function unlockAccount(array $input): User
    {
        $challenge = $this->consumeVerifiedOtpChallenge->handle(
            $input['otp_challenge_uuid'],
            OtpPurpose::AccountUnlock,
            $input['verification_token'],
        );

        $user = $challenge->user ?: User::query()->where('email', $challenge->email)->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['Compte introuvable.']]);
        }

        $user->forceFill([
            'failed_pin_attempts' => 0,
            'locked_until' => null,
            'status' => UserStatus::Active,
        ])->save();

        return $user;
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null, country_code?: string|null, birth_date?: string|null}  $input
     */
    public function updateProfile(User $user, array $input): User
    {
        $emailChanged = Str::lower($input['email']) !== Str::lower($user->email);

        $rawPhone = $input['phone'] ?? null;
        $normalizedPhone = $rawPhone !== null
            ? $this->identityResolver->normalizePhone($rawPhone, $input['country_code'] ?? null)
            : $user->phone;

        $user->forceFill([
            'name' => $input['name'],
            'email' => Str::lower($input['email']),
            'phone' => $normalizedPhone,
            'country_code' => $input['country_code'] ?? $user->country_code,
            'birth_date' => $input['birth_date'] ?? $user->birth_date,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        return $user->refresh();
    }

    /**
     * @param  array{current_pin: string, new_pin: string}  $input
     */
    public function changePin(User $user, array $input): User
    {
        if (! $user->pin_hash || ! Hash::check($input['current_pin'], $user->pin_hash)) {
            throw ValidationException::withMessages([
                'current_pin' => ['Le PIN actuel est incorrect.'],
            ]);
        }

        $user->forceFill(['pin_hash' => Hash::make($input['new_pin'])])->save();

        return $user->refresh();
    }

    /**
     * @param  array{otp_challenge_uuid: string, verification_token: string, pin: string}  $input
     */
    public function resetPin(array $input): User
    {
        $challenge = $this->consumeVerifiedOtpChallenge->handle(
            $input['otp_challenge_uuid'],
            OtpPurpose::PinReset,
            $input['verification_token'],
        );

        $user = $challenge->user ?: User::query()->where('email', $challenge->email)->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => ['Compte introuvable.']]);
        }

        $user->forceFill(['pin_hash' => Hash::make($input['pin'])])->save();

        return $user;
    }

    /**
     * @param  array{device_name: string, platform: string, device_fingerprint: string, push_token?: string|null}  $input
     * @return array{user: User, device: UserDevice, token: string, token_expires_at: CarbonInterface|null}
     */
    private function issueSessionForDevice(User $user, array $input): array
    {
        $device = $this->upsertTrustedDevice->handle($user, [
            'name' => $input['device_name'],
            'platform' => $input['platform'],
            'fingerprint' => $input['device_fingerprint'],
            'push_token' => $input['push_token'] ?? null,
        ]);
        $token = $this->issueDeviceToken->handle($user, $device);

        return [
            'user' => $user->refresh(),
            'device' => $device,
            'token' => $token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at,
        ];
    }

    private function defaultNameFromEmail(string $email): string
    {
        return Str::headline(Str::before($email, '@')) ?: 'KORA Passenger';
    }

    private const MAX_PIN_ATTEMPTS = 5;

    private function registerFailedPinAttempt(User $user): bool
    {
        $attempts = $user->failed_pin_attempts + 1;

        if ($attempts >= self::MAX_PIN_ATTEMPTS) {
            $user->forceFill([
                'failed_pin_attempts' => $attempts,
                'locked_until' => now()->addMinutes(30),
                'status' => UserStatus::Locked,
            ])->save();

            return true;
        }

        $user->forceFill(['failed_pin_attempts' => $attempts])->save();

        return false;
    }
}
