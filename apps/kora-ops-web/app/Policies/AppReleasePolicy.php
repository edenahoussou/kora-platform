<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\AppDistribution\Data\AppReleasePermission;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Policies\Concerns\AuthorizesPermissions;

/**
 * Écran « Versions de l'application » (MEZ-150). Les endpoints publics de
 * téléchargement et la vérification de mise à jour ne passent pas par
 * cette policy : ils sont volontairement ouverts (les P501 n'ont pas de
 * session OPS Web).
 */
class AppReleasePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, AppReleasePermission::View->value);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, AppReleasePermission::Manage->value);
    }

    public function update(User $user, AppRelease $release): bool
    {
        return $this->allows($user, AppReleasePermission::Manage->value);
    }

    public function archive(User $user, AppRelease $release): bool
    {
        return $this->allows($user, AppReleasePermission::Manage->value);
    }

    public function restore(User $user, AppRelease $release): bool
    {
        return $this->allows($user, AppReleasePermission::Manage->value);
    }
}
