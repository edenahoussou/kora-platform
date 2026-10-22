<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Data\PermissionCatalog;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\UserPreference;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;

class OpsProfileService
{
    public function __construct(
        private readonly UserSessionService $sessionService,
    ) {}

    /**
     * Agrège toutes les props explicites du hub profil (identité, sécurité,
     * sessions/appareils, préférences) pour le rendu Inertia.
     *
     * @return array<string, mixed>
     */
    public function props(User $user, string $currentSessionId): array
    {
        return [
            'profile' => $this->profile($user),
            'security' => $this->security($user),
            'sessions' => $this->sessionService->listForUser($user, $currentSessionId),
            'preferences' => $this->preferences($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        $role = $user->roles()->first();
        $systemRole = $role === null ? null : SystemRole::tryFrom((string) $role->name);
        $roleLabel = $role?->label;

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'emailVerified' => $user->hasVerifiedEmail(),
            'emailLockedReason' => 'Votre adresse e-mail constitue l\'identifiant racine du compte. Pour des raisons de traçabilité SYSCOHADA et de sécurité des accès, sa modification est réservée à la Direction des Systèmes d\'Information.',
            'phone' => $user->phone,
            'countryCode' => $user->country_code,
            'jobTitle' => $user->job_title,
            'department' => $user->department,
            'location' => $user->location,
            'timezone' => $user->preference()->value('timezone'),
            'avatar' => $user->getFirstMediaUrl('avatar', 'thumb') ?: null,
            'status' => $user->status->value,
            'statusLabel' => $this->statusLabel($user),
            'roleCode' => $role?->name,
            'roleLabel' => $systemRole?->translatedLabel() ?? $roleLabel ?? 'Utilisateur',
            'agency' => $user->agency()->value('name') ?? 'Direction Générale',
            'memberSince' => $user->created_at?->translatedFormat('d F Y'),
            'permissions' => $this->permissions($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function security(User $user): array
    {
        return [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'twoFactorEnabled' => Features::canManageTwoFactorAuthentication()
                ? $user->hasEnabledTwoFactorAuthentication()
                : false,
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => $this->passkeys($user),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'lastPasswordChange' => $user->password_changed_at?->diffForHumans(),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, authenticator: string|null, created_at_diff: string|null, last_used_at_diff: string|null}>
     */
    private function passkeys(User $user): Collection
    {
        if (! Features::canManagePasskeys()) {
            return collect();
        }

        return $user->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn (Passkey $passkey): array => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at?->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function preferences(User $user): array
    {
        $preference = $user->preference()->first() ?? new UserPreference([
            'push_notifications' => UserPreference::defaultPushNotificationsFor($user),
            'whatsapp_notifications' => true,
            'sms_notifications' => true,
            'email_notifications' => true,
            'locale' => 'fr',
            'theme' => 'light',
            'timezone' => 'Africa/Porto-Novo',
            'dispatch_alerts' => true,
            'cashier_alerts' => true,
            'security_alerts' => true,
        ]);

        return [
            'locale' => $preference->locale,
            'timezone' => $preference->timezone ?? 'Africa/Porto-Novo',
            'pushAlerts' => $preference->push_notifications,
            'whatsappAlerts' => $preference->whatsapp_notifications,
            'emailDigest' => $preference->email_notifications,
            'dispatchAlerts' => $preference->dispatch_alerts,
            'cashierAlerts' => $preference->cashier_alerts,
            'securityAlerts' => $preference->security_alerts,
        ];
    }

    /**
     * @return Collection<int, array{code: string, label: string, category: string}>
     */
    private function permissions(User $user): Collection
    {
        return $user->getAllPermissions()
            ->sortBy('name')
            ->map(fn ($permission): array => [
                'code' => (string) $permission->name,
                'label' => PermissionCatalog::label($permission->name),
                'category' => PermissionCatalog::domainLabel((string) str($permission->name)->before('.')),
            ])
            ->values();
    }

    private function statusLabel(User $user): string
    {
        return match ($user->status) {
            UserStatus::Suspended => 'Compte Suspendu',
            UserStatus::Locked => 'Compte Verrouillé',
            default => 'Compte Actif',
        };
    }
}
