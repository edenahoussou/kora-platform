<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointSale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class KoraPointAccess
{
    /**
     * Tells whether the user holds a partner-facing role (agent, supervisor or treasurer).
     */
    public function isPartner(User $user): bool
    {
        return $user->hasAnyRole(['kora_point_agent', 'kora_point_supervisor', 'kora_point_treasurer']);
    }

    public function isOrganizationPartner(User $user): bool
    {
        return $user->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer']);
    }

    public function isOrganizationSupervisor(User $user): bool
    {
        return $user->hasRole('kora_point_supervisor');
    }

    /**
     * Throws an authorization error when the user is inactive or lacks the given permission.
     */
    public function authorize(User $user, string $permission): void
    {
        if ($user->status !== UserStatus::Active || ! $user->can($permission)) {
            throw new AuthorizationException;
        }
    }

    public function authorizeInternal(User $user, string $permission): void
    {
        $this->authorize($user, $permission);

        if ($this->isPartner($user)) {
            throw new AuthorizationException;
        }
    }

    /** @return Builder<KoraPoint> */
    /**
     * Returns the points query scoped to the actor visibility (internal or partner role).
     *
     * @return Builder<KoraPoint>
     */
    public function points(User $user): Builder
    {
        $query = KoraPoint::query();
        if ($user->status !== UserStatus::Active || ! $user->can('kora-points.view')) {
            return $query->whereRaw('1 = 0');
        }
        if (! $this->isPartner($user)) {
            return $query;
        }
        if ($user->hasRole('kora_point_agent')) {
            return $query->where('agency_id', $user->agency_id ?? 0);
        }

        return $query->where('organization_id', $user->kora_point_organization_id ?? 0)
            ->whereHas('organization', fn (Builder $organization) => $organization->where('status', 'active'));
    }

    /**
     * Tells whether the actor may see the given point.
     */
    public function viewPoint(User $user, KoraPoint $point): bool
    {
        return $this->points($user)->whereKey($point->getKey())->exists();
    }

    /**
     * Tells whether the actor may see the given organization.
     */
    public function viewOrganization(User $user, KoraPointOrganization $organization): bool
    {
        return $user->status === UserStatus::Active && $organization->status === 'active' && $user->can('kora-points.view')
            && (! $this->isPartner($user) || (! $user->hasRole('kora_point_agent') && $user->kora_point_organization_id === $organization->getKey()));
    }

    /** @return Builder<KoraPointSale> */
    /**
     * Returns the sales query scoped to the actor visibility (internal or partner role).
     *
     * @return Builder<KoraPointSale>
     */
    public function sales(User $user): Builder
    {
        $query = KoraPointSale::query();
        if ($user->status !== UserStatus::Active || ! $user->can('kora-points.view')) {
            return $query->whereRaw('1 = 0');
        }
        if (! $this->isPartner($user)) {
            return $query;
        }
        if ($user->hasRole('kora_point_agent')) {
            return $query->whereHas('point', fn (Builder $points) => $points->where('agency_id', $user->agency_id ?? 0));
        }

        return $query->where('organization_id', $user->kora_point_organization_id ?? 0)
            ->whereHas('point.organization', fn (Builder $organization) => $organization->where('status', 'active'));
    }

    /**
     * Throws an authorization error when the sale is outside the actor visibility scope.
     */
    public function requireSale(User $user, KoraPointSale $sale): void
    {
        if (! $this->sales($user)->whereKey($sale->getKey())->exists()) {
            throw new AuthorizationException;
        }
    }

    /**
     * Throws an authorization error when the point is outside the actor visibility scope.
     */
    public function requirePoint(User $user, KoraPoint $point): void
    {
        if (! $this->viewPoint($user, $point)) {
            throw new AuthorizationException;
        }
    }

    /**
     * Throws an authorization error when the organization is outside the actor visibility scope.
     */
    public function requireOrganization(User $user, KoraPointOrganization $organization): void
    {
        if (! $this->viewOrganization($user, $organization)) {
            throw new AuthorizationException;
        }
    }

    /**
     * Verifies remittance rights: agents of organization-attached points cannot declare.
     */
    public function declare(User $user, KoraPointSale $sale): void
    {
        $this->authorize($user, 'kora-points.remit');
        $this->requireSale($user, $sale);
        if ($this->isPartner($user) && $user->hasRole('kora_point_agent') && $sale->point->organization_id !== null) {
            throw new AuthorizationException('Les reversements de cette organisation relèvent de son trésorier.');
        }
    }
}
