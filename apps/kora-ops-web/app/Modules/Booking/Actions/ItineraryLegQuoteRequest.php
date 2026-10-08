<?php

namespace App\Modules\Booking\Actions;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;

/**
 * Leg data for a multi-leg quote request.
 *
 * `direction` distinguishes the outbound trip from the return trip of an
 * aller-retour: a quote can carry legs from both directions at once, all
 * priced and held together as a single reservation.
 */
final class ItineraryLegQuoteRequest
{
    public function __construct(
        public readonly Departure $departure,
        public readonly Stop $originStop,
        public readonly Stop $destinationStop,
        public readonly string $direction = 'outbound',
    ) {}
}
