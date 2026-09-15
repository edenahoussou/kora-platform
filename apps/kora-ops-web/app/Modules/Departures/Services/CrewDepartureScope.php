<?php

namespace App\Modules\Departures\Services;

use App\Models\User;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Policies\Concerns\AuthorizesPermissions;
use Illuminate\Database\Eloquent\Builder;

/**
 * Départs visibles pour un membre d'équipage terrain (convoyeur,
 * chauffeur, chauffeur second, mécanicien) sur OPS Mobile : la permission
 * `departures.view` ne suffit pas — encore faut-il être personnellement
 * affecté au départ, et celui-ci ne doit pas être annulé.
 */
class CrewDepartureScope
{
    use AuthorizesPermissions;

    /** @return Builder<Departure> */
    public function query(User $user, string $permission = 'departures.view'): Builder
    {
        $query = Departure::query()->where('status', '!=', DepartureStatus::Cancelled->value);

        if (! $this->allows($user, $permission)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('driver_id', $user->id)
                ->orWhere('secondary_driver_id', $user->id)
                ->orWhere('conductor_id', $user->id)
                ->orWhere('mechanic_id', $user->id);
        });
    }

    public function canAccess(User $user, Departure $departure): bool
    {
        return $this->query($user)->whereKey($departure->getKey())->exists();
    }

    /**
     * OPS Mobile boarding : au-delà de l'affectation, il faut la
     * permission `boarding.scan` spécifiquement (accordée au convoyeur,
     * pas au chauffeur ni au mécanicien).
     */
    public function canScanBoarding(User $user, Departure $departure): bool
    {
        return $this->query($user, 'boarding.scan')->whereKey($departure->getKey())->exists();
    }

    /**
     * OPS Mobile seat-releases (descente anticipée, clôture no-show) :
     * permission `seat-releases.create` spécifiquement.
     */
    public function canReleaseSeat(User $user, Departure $departure): bool
    {
        return $this->query($user, 'seat-releases.create')->whereKey($departure->getKey())->exists();
    }
}
