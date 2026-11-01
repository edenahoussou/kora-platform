<?php

namespace App\Modules\KoraPoints\Policies;

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Services\KoraPointAccess;

class KoraPointOrganizationPolicy
{
    public function __construct(private readonly KoraPointAccess $access) {}

    /**
     * Tells whether the actor may see the given organization.
     */
    public function view(User $user, KoraPointOrganization $organization): bool
    {
        return $this->access->viewOrganization($user, $organization);
    }
}
