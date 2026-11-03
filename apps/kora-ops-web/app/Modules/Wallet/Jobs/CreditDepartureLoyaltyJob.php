<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Departures\Models\Departure;
use App\Modules\Wallet\Actions\ProcessDepartureLoyaltyAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreditDepartureLoyaltyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $departureId) {}

    public function handle(ProcessDepartureLoyaltyAction $processDepartureLoyalty): void
    {
        $departure = Departure::query()->find($this->departureId);

        if ($departure !== null) {
            $processDepartureLoyalty->handle($departure);
        }
    }
}
