<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Departures\Models\Departure;

/**
 * Crédite les points de toutes les jambes embarquées d'un départ clôturé.
 */
final class ProcessDepartureLoyaltyAction
{
    /**
     * @param  CreditLegPointsAction  $creditLegPoints  Crédit des jambes
     */
    public function __construct(private readonly CreditLegPointsAction $creditLegPoints) {}

    /**
     * Crédite les points de toutes les jambes embarquées du départ.
     *
     * @param  Departure  $departure  Départ clôturé
     * @return int Points crédités
     */
    public function handle(Departure $departure): int
    {
        $reservations = Reservation::query()
            ->whereHas('segments', fn ($query) => $query
                ->where('departure_id', $departure->getKey())
                ->where('status', ReservationSegmentStatus::Boarded->value))
            ->with(['segments', 'passengers.user', 'payer'])
            ->get();

        $credited = 0;

        foreach ($reservations as $reservation) {
            $credited += $this->creditLegPoints->handle($reservation);
        }

        return $credited;
    }
}
