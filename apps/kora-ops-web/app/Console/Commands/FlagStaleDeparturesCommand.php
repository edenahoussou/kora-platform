<?php

namespace App\Console\Commands;

use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class FlagStaleDeparturesCommand extends Command
{
    protected $signature = 'bus:flag-stale-departures';

    protected $description = 'Report scheduled departures that are stale and require operational review';

    public function handle(): int
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $thresholdHours = max(1, (int) config('kora.departures.sellability.stale_threshold_hours', 24));
        $staleCount = 0;

        Departure::query()
            ->where('status', DepartureStatus::Scheduled->value)
            ->whereDate('departure_date', '<=', $now->toDateString())
            ->lazyById(100)
            ->each(function (Departure $departure) use ($now, $thresholdHours, &$staleCount): void {
                $effectiveDepartureAt = $departure->scheduledDepartureAt()->addMinutes((int) $departure->delay_minutes);
                if ($effectiveDepartureAt->addHours($thresholdHours)->greaterThanOrEqualTo($now)) {
                    return;
                }

                $staleCount++;
                $this->warn(sprintf(
                    'Stale departure %s: effective departure %s remains scheduled.',
                    $departure->uuid,
                    $effectiveDepartureAt->toIso8601String(),
                ));
            });

        $this->info("{$staleCount} stale departure(s) found.");

        return self::SUCCESS;
    }
}
