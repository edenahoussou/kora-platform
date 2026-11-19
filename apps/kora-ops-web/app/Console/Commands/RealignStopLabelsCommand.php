<?php

namespace App\Console\Commands;

use App\Modules\BusNetwork\Actions\FindOrCreatePhysicalStopAction;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RealignStopLabelsCommand extends Command
{
    protected $signature = 'bus:realign-stop-labels {--apply : Write the canonical physical stop labels onto line stops}';

    protected $description = 'Report (or fix with --apply) line stops whose label diverges from their physical stop, and city spelling variants';

    public function handle(): int
    {
        $diverging = Stop::query()
            ->with('physicalStop')
            ->whereNotNull('physical_stop_id')
            ->get()
            ->filter(fn (Stop $stop): bool => $stop->physicalStop !== null && $this->diverges($stop, $stop->physicalStop));

        $this->table(
            ['Stop', 'Line id', 'Stop name / city', 'Physical stop name / city'],
            $diverging->map(fn (Stop $stop): array => [
                $stop->uuid,
                $stop->line_id,
                "{$stop->name} / {$stop->city}",
                "{$stop->physicalStop?->name} / {$stop->physicalStop?->city}",
            ])->all(),
        );
        $this->info("{$diverging->count()} line stop(s) diverge from their physical stop.");

        $variants = PhysicalStop::query()
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->groupBy(fn (string $city): string => FindOrCreatePhysicalStopAction::normalize($city))
            ->filter(fn ($group): bool => $group->count() > 1);

        foreach ($variants as $group) {
            $this->warn('City spelling variants: '.$group->implode(' | '));
        }

        if (! $this->option('apply')) {
            $this->comment('Dry run: nothing written. Re-run with --apply after review.');

            return self::SUCCESS;
        }

        DB::transaction(fn () => $diverging->each(
            fn (Stop $stop) => $stop->update(PhysicalStop::canonicalStopAttributes($stop->physicalStop)),
        ));
        $this->info("{$diverging->count()} line stop(s) realigned. City variants must be merged manually.");

        return self::SUCCESS;
    }

    private function diverges(Stop $stop, PhysicalStop $physicalStop): bool
    {
        foreach (PhysicalStop::canonicalStopAttributes($physicalStop) as $key => $canonical) {
            $current = $stop->getAttribute($key);
            $differs = is_float($canonical) || $canonical === null
                ? ($current === null) !== ($canonical === null) || abs((float) $current - (float) $canonical) > 1e-7
                : (string) $current !== $canonical;

            if ($differs) {
                return true;
            }
        }

        return false;
    }
}
