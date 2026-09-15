<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Policies\Concerns\AuthorizesPermissions;

class DeparturePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'departures.view');
    }

    public function view(User $user, Departure $departure): bool
    {
        return $this->allows($user, 'departures.view');
    }

    /**
     * OPS Mobile : au-delà de la permission, il faut être personnellement
     * affecté au départ (chauffeur, chauffeur second, convoyeur ou
     * mécanicien) et celui-ci ne doit pas être annulé.
     */
    public function viewAsCrew(User $user, Departure $departure): bool
    {
        return app(CrewDepartureScope::class)->canAccess($user, $departure);
    }

    /**
     * OPS Mobile : peut valider un embarquement sur ce départ — affecté
     * au départ (non annulé) et permission `boarding.scan` (convoyeur).
     */
    public function scanBoarding(User $user, Departure $departure): bool
    {
        return app(CrewDepartureScope::class)->canScanBoarding($user, $departure);
    }

    /**
     * OPS Mobile : peut enregistrer une descente anticipée ou clôturer
     * les no-show sur ce départ — affecté au départ (non annulé) et
     * permission `seat-releases.create`.
     */
    public function releaseSeat(User $user, Departure $departure): bool
    {
        return app(CrewDepartureScope::class)->canReleaseSeat($user, $departure);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'departures.manage');
    }

    public function update(User $user, Departure $departure): bool
    {
        return $this->allows($user, 'departures.manage');
    }

    public function recordDelay(User $user, Departure $departure): bool
    {
        return $this->allows($user, 'departures.manage');
    }

    public function delete(User $user, Departure $departure): bool
    {
        return $this->allows($user, 'departures.cancel');
    }
}
