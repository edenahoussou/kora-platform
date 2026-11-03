<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Wallet\Actions\ProcessDepartureLoyaltyAction;
use Illuminate\Console\Command;

class CreditPendingLegsCommand extends Command
{
    protected $signature = 'wallet:credit-pending-legs {--days=30 : Fenêtre de rattrapage en jours}';

    protected $description = 'Crédite les points des jambes embarquées des départs arrivés non traités';

    public function handle(ProcessDepartureLoyaltyAction $processDepartureLoyalty): int
    {
        $days = max(1, (int) $this->option('days'));
        $credited = 0;
        $departures = 0;

        Departure::query()
            ->where('status', DepartureStatus::Arrived->value)
            ->where('arrived_at', '>=', now()->subDays($days))
            ->whereHas('reservationSegments', fn ($query) => $query->where('status', 'boarded'))
            ->orderBy('id')
            ->chunkById(50, function ($chunk) use (&$credited, &$departures, $processDepartureLoyalty): void {
                foreach ($chunk as $departure) {
                    $credited += $processDepartureLoyalty->handle($departure);
                    $departures++;
                }
            });

        $this->info("Traité {$departures} départ(s), crédité {$credited} point(s).");

        return self::SUCCESS;
    }
}
