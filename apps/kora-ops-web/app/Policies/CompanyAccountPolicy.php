<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserStatus;

use function Illuminate\Support\enum_value;

class CompanyAccountPolicy
{
    public function viewAny(User $user): bool
    {
        // Le rôle passenger porte company-accounts.view pour l'API membre :
        // un utilisateur cantonné à ce seul rôle n'a pas accès à l'index admin.
        return enum_value($user->status) === UserStatus::Active->value
            && $user->can('company-accounts.view')
            && (! $user->hasRole(SystemRole::Passenger->value)
                || $user->hasAnyRole(array_map(
                    fn (SystemRole $role): string => $role->value,
                    SystemRole::internalRoles(),
                )));
    }

    public function view(User $user, CompanyAccount $account): bool
    {
        if (enum_value($user->status) !== UserStatus::Active->value) {
            return false;
        }
        if ($user->can('company-accounts.view') && $user->can('company-credit.view')) {
            return true;
        }

        return $account->members()->where('user_id', $user->getKey())->where('status', 'active')->exists();
    }

    public function manage(User $user): bool
    {
        return enum_value($user->status) === UserStatus::Active->value && $user->can('company-accounts.manage');
    }
}
