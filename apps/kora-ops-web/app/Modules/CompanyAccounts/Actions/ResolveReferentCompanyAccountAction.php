<?php

namespace App\Modules\CompanyAccounts\Actions;

use App\Models\User;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Data\CompanyMemberRole;
use App\Modules\CompanyAccounts\Data\CompanyMemberStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\IdentityAccess\Data\SystemRole;

/**
 * Résout le compte organisationnel dont l'utilisateur est le référent actif.
 */
class ResolveReferentCompanyAccountAction
{
    public function handle(?User $user): ?CompanyAccount
    {
        if ($user === null) {
            return null;
        }

        return CompanyAccount::query()
            ->where('status', CompanyAccountStatus::Active->value)
            ->whereHas('members', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('role', CompanyMemberRole::Referent->value)
                ->where('status', CompanyMemberStatus::Active->value))
            ->first();
    }

    /**
     * Utilisateur qui n'a que l'espace entreprise pour périmètre : référent
     * actif sans aucun rôle interne.
     */
    public function isPortalOnlyUser(?User $user): bool
    {
        if ($user === null || $user->hasAnyRole(array_map(
            fn (SystemRole $role): string => $role->value,
            SystemRole::internalRoles(),
        ))) {
            return false;
        }

        return $this->handle($user) !== null;
    }
}
