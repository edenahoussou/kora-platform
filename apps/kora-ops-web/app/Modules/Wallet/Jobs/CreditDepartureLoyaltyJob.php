<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Departures\Models\Departure;
use App\Modules\Wallet\Actions\ProcessDepartureLoyaltyAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Crédite les points des jambes d'un départ clôturé.
 */
class CreditDepartureLoyaltyJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $departureId  Identifiant interne du départ clôturé
     */
    public function __construct(public int $departureId) {}

    /**
     * Exécute le traitement de fidélité du départ.
     *
     * @param  ProcessDepartureLoyaltyAction  $processDepartureLoyalty  Traitement de fidélité du départ
     */
    public function handle(ProcessDepartureLoyaltyAction $processDepartureLoyalty): void
    {
        $departure = Departure::query()->find($this->departureId);

        if ($departure !== null) {
            $processDepartureLoyalty->handle($departure);
        }
    }
}
