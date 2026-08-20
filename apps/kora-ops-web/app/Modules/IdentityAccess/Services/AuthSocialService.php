<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\IssueDeviceTokenAction;
use App\Modules\IdentityAccess\Actions\UpsertTrustedDeviceAction;
use App\Modules\IdentityAccess\Data\OAuthProvider;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Exceptions\AuthSuspendedException;
use App\Modules\IdentityAccess\Models\UserSocialAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

class AuthSocialService
{
    public function __construct(
        private IssueDeviceTokenAction $issueDeviceToken,
        private UpsertTrustedDeviceAction $upsertTrustedDevice,
    ) {}

    /**
     * Authentifie (ou crée) un utilisateur via un provider OAuth 2.0.
     *
     * Le token fourni par le SDK mobile est validé côté backend via
     * Socialite::driver($provider)->userFromToken($token). Le profil
     * récupéré (ID provider, nom, email, avatar) permet de retrouver
     * ou créer le User, lier le compte social, télécharger l'avatar
     * via Spatie Media Library et émettre un token Sanctum.
     *
     * @param  array{provider: OAuthProvider, provider_token: string, device_name: string, platform: string, device_fingerprint: string, push_token?: string|null}  $input
     * @return array{user: User, device: mixed, token: string, token_expires_at: CarbonInterface|null, created: bool}
     *
     * @throws AuthSuspendedException Si le compte est suspendu.
     * @throws Throwable Si le token OAuth est invalide ou le provider injoignable.
     */
    public function loginWithOAuth(array $input): array
    {
        $socialUser = Socialite::driver($input['provider']->value)
            ->userFromToken($input['provider_token']); // @phpstan-ignore-line — userFromToken() est défini sur Two\Provider, pas sur l'interface Provider

        $created = false;
        $user = $this->findOrCreateUser($input['provider'], $socialUser, $created);

        $status = $user->status;
        if ($status === UserStatus::Suspended) {
            throw new AuthSuspendedException;
        }

        // Réactiver un compte verrouillé via OAuth : le passager prouve
        // son identité auprès du provider, on déverrouille.
        if ($status === UserStatus::Locked) {
            $user->forceFill([
                'failed_pin_attempts' => 0,
                'locked_until' => null,
                'status' => UserStatus::Active,
            ])->save();
        }

        $this->syncSocialAccount($user, $input['provider'], $socialUser);
        $this->syncAvatar($user, $socialUser);

        $device = $this->upsertTrustedDevice->handle($user, [
            'name' => $input['device_name'],
            'platform' => $input['platform'],
            'fingerprint' => $input['device_fingerprint'],
            'push_token' => $input['push_token'] ?? null,
        ]);

        // Connexion OAuth = appareil de confiance immédiat (le provider
        // a déjà validé l'identité de l'utilisateur).
        if (! $device->trusted_at) {
            $device->forceFill(['trusted_at' => now()])->save();
        }

        $token = $this->issueDeviceToken->handle($user, $device);

        return [
            'user' => $user->refresh(),
            'device' => $device,
            'token' => $token->plainTextToken,
            'token_expires_at' => $token->accessToken->expires_at,
            'created' => $created,
        ];
    }

    /**
     * Recherche un utilisateur via son compte social lié, sinon par
     * email, sinon crée un nouveau User avec le rôle passenger.
     */
    private function findOrCreateUser(OAuthProvider $provider, SocialiteUser $socialUser, bool &$created): User
    {
        $account = UserSocialAccount::query()
            ->where('provider', $provider->value)
            ->where('provider_user_id', $socialUser->getId())
            ->first();

        if ($account) {
            return $account->user;
        }

        $email = $socialUser->getEmail()
            ? Str::lower($socialUser->getEmail())
            : null;

        if ($email) {
            $existing = User::query()->where('email', $email)->first();
            if ($existing) {
                return $existing;
            }
        }

        $name = $socialUser->getName() ?: $socialUser->getNickname() ?: $this->defaultName($provider, $socialUser);

        $user = User::create([
            'name' => $name,
            'email' => $email ?? $this->syntheticEmail($provider, $socialUser),
            'status' => UserStatus::Active,
            'password' => Str::password(32),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('passenger');
        $created = true;

        return $user;
    }

    /**
     * Crée ou met à jour le lien entre le User et son compte social.
     */
    private function syncSocialAccount(User $user, OAuthProvider $provider, SocialiteUser $socialUser): void
    {
        UserSocialAccount::updateOrCreate(
            [
                'provider' => $provider->value,
                'provider_user_id' => $socialUser->getId(),
            ],
            [
                'user_id' => $user->id,
                'nickname' => $socialUser->getNickname(),
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn
                    ? now()->addSeconds($socialUser->expiresIn)
                    : null,
                'raw_attributes' => $socialUser->getRaw(),
            ],
        );
    }

    /**
     * Télécharge l'avatar du provider via Spatie Media Library si
     * l'utilisateur n'en a pas déjà un.
     */
    private function syncAvatar(User $user, SocialiteUser $socialUser): void
    {
        $avatarUrl = $socialUser->getAvatar();

        if (! $avatarUrl || $user->getFirstMedia('avatar')) {
            return;
        }

        try {
            $user->addMediaFromUrl($avatarUrl)->toMediaCollection('avatar');
        } catch (Throwable) {
            // L'avatar social est optionnel : on ignore silencieusement
            // les échecs de téléchargement (URL expirée, réseau, etc.).
        }
    }

    private function defaultName(OAuthProvider $provider, SocialiteUser $socialUser): string
    {
        $nickname = $socialUser->getNickname();

        return $nickname
            ? Str::headline($nickname)
            : 'Passager KORA';
    }

    /**
     * Certains providers (TikTok) ne retournent pas toujours d'email.
     * On génère un identifiant synthétique unique pour ne pas bloquer
     * la création du compte ; l'email réel pourra être complété plus
     * tard dans le profil.
     */
    private function syntheticEmail(OAuthProvider $provider, SocialiteUser $socialUser): string
    {
        return sprintf(
            '%s.%s@social.korabus.app',
            $provider->value,
            $socialUser->getId(),
        );
    }
}
