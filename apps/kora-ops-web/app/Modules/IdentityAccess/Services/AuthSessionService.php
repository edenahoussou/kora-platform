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
use App\Modules\IdentityAccess\Exceptions\AuthBusinessException;
use App\Modules\IdentityAccess\Exceptions\AuthLockedException;
use App\Modules\IdentityAccess\Exceptions\AuthSuspendedException;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\Notifications\Channels\OtpCodeChannel;
use App\Modules\Wallet\Actions\AttachReferralCodeAction;
use App\Modules\Wallet\Actions\FindOrCreateWalletAction;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        private FindOrCreateWalletAction $findOrCreateWallet,
        private AttachReferralCodeAction $attachReferralCode,
    ) {}

    /**
     * @param  array{otp_challenge_uuid: string, verification_token: string, pin: string, device_name: string, platform: string, device_fingerprint: string, push_token?: string|null, name?: string|null, email?: string|null, phone?: string|null, country_code?: string|null, birth_date?: string|null, referral_code?: string|null}  $input
     * @return array{user: User, device: UserDevice, token: string, token_expires_at: CarbonInterface|null}
     */
    public function completeRegistration(array $input): array
    {
        return DB::transaction(fn (): array => $this->registerPassenger($input));
    }

    /**
     * @param  array{otp_challenge_uuid: string, verification_token: string, pin: string, device_name: string, platform: string, device_fingerprint: string, push_token?: string|null, name?: string|null, email?: string|null, phone?: string|null, country_code?: string|null, birth_date?: string|null, referral_code?: string|null}  $input
     * @return array{user: User, device: UserDevice, token: string, token_expires_at: CarbonInterface|null}
     */
    private function registerPassenger(array $input): array
    {
        $challenge = $this->consumeVerifiedOtpChallenge->handle(
            $input['otp_challenge_uuid'],
            OtpPurpose::Registration,
            $input['verification_token'],
            lockForUpdate: true,
        );

        // L'identifiant du challenge fait foi : c'est lui qui a reçu le
        // code. L'autre coordonnée peut être fournie à la complétion —
        // email pour une inscription téléphone, téléphone pour email.
        $identifier = $challenge->identifier;
        $challengeIsEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

        $phone = $challengeIsEmail
            ? $this->normalizeOptionalPhone($input['phone'] ?? null, $input['country_code'] ?? null)
            : $identifier;
        $email = $challengeIsEmail
            ? Str::lower($identifier)
            : $this->normalizeOptionalEmail($input['email'] ?? null);

        $phoneOwner = $phone !== null ? User::query()->where('phone', $phone)->first() : null;
        $emailOwner = $email !== null ? User::query()->where('email', $email)->first() : null;

        $user = $challengeIsEmail ? $emailOwner : $phoneOwner;

        // La coordonnée secondaire, quand elle est fournie, ne peut pas
        // appartenir à un compte différent de celui qu'on complète.
        if ($challengeIsEmail && $phoneOwner !== null && $phoneOwner->isNot($user)) {
            throw new AuthBusinessException('phone_taken', 'Ce numéro de téléphone est déjà utilisé par un autre compte.');
        }

        if (! $challengeIsEmail && $emailOwner !== null && $emailOwner->isNot($user)) {
            throw new AuthBusinessException('email_taken', 'Cette adresse email est déjà utilisée par un autre compte.');
        }

        if ($user !== null && $user->pin_hash) {
            // Un compte actif ne se « réinscrit » pas : la voie normale est
            // la connexion (ou le PIN oublié), pas un claim silencieux.
            throw new AuthBusinessException(
                'account_exists',
                'Un compte existe déjà avec cet identifiant. Connectez-vous.',
                409,
            );
        }

        $referralCode = trim($input['referral_code'] ?? '');

        if ($user !== null && $referralCode !== '') {
            throw ValidationException::withMessages([
                'referral_code' => 'Le code de parrainage est réservé à la création d’un nouveau compte.',
            ]);
        }

        if ($user !== null) {
            // Claim : compte sans PIN (réservation assistée, OAuth sans
            // PIN) — le code reçu prouve la possession de l'identifiant.
            $user->forceFill([
                'name' => filled($input['name'] ?? null) ? $input['name'] : $user->name,
                'email' => $email ?? $user->email,
                'phone' => $phone ?? $user->phone,
                'country_code' => $input['country_code'] ?? $user->country_code,
                'birth_date' => $input['birth_date'] ?? $user->birth_date,
                'pin_hash' => Hash::make($input['pin']),
                'email_verified_at' => $challengeIsEmail
                    ? ($user->email_verified_at ?? now())
                    : $user->email_verified_at,
                'status' => UserStatus::Active,
            ])->save();
        } else {
            $name = trim((string) ($input['name'] ?? ''));

            if (! $challengeIsEmail && $name === '') {
                throw new AuthBusinessException(
                    'name_required',
                    'Le nom est obligatoire pour créer votre compte.',
                );
            }

            $user = User::query()->create([
                'registration_origin' => $challengeIsEmail ? 'email' : 'phone',
                'name' => $name !== '' ? $name : $this->defaultNameFromEmail((string) $email),
                'email' => $email ?? 'traveler+'.Str::uuid().'@'.OtpCodeChannel::PLACEHOLDER_EMAIL_DOMAIN,
                'phone' => $phone,
                'country_code' => $input['country_code'] ?? null,
                'birth_date' => $input['birth_date'] ?? null,
                'status' => UserStatus::Active,
                'email_verified_at' => $challengeIsEmail ? now() : null,
                'password' => Str::password(32),
                'pin_hash' => Hash::make($input['pin']),
            ]);
        }

        if ($user->wasRecentlyCreated || $user->roles()->doesntExist()) {
            $user->assignRole('passenger');
        }

        $challenge->update(['user_id' => $user->id]);

        if ($referralCode !== '') {
            $wallet = $this->findOrCreateWallet->handle($user);
            $this->attachReferralCode->handle($wallet, $referralCode);
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

        $user = $challenge->user ?: $this->identifierResolver->findUser($challenge->identifier);

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

        $user = $challenge->user ?: $this->identifierResolver->findUser($challenge->identifier);

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

    private function normalizeOptionalPhone(?string $phone, ?string $countryCode): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        return $this->identityResolver->normalizePhone($phone, $countryCode);
    }

    private function normalizeOptionalEmail(?string $email): ?string
    {
        return $email !== null && trim($email) !== '' ? Str::lower(trim($email)) : null;
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
