<?php

namespace App\Console\Commands;

use App\Jobs\MaintainDepartureProgramme;
use App\Jobs\ProcessProgrammingSelection;
use App\Modules\Departures\Models\DepartureProgramme;
use App\Modules\Departures\Models\ProgrammingRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MaintainDepartureProgrammesCommand extends Command
{
    protected $signature = 'bus:maintain-programmes';

    protected $description = 'Maintient l’horizon des programmes validés et publie les départs admissibles.';

    public function handle(): int
    {
        if (! Schema::hasColumn('departure_programmes', 'automatic_approved_by')) {
            return self::SUCCESS;
        }
        ProgrammingRun::query()->whereIn('operation', ['publish', 'assign', 'exclude', 'change'])->whereIn('status', ['queued', 'running'])->where('updated_at', '<', now()->subMinutes(5))->eachById(fn ($run) => ProcessProgrammingSelection::dispatch($run->id));
        DepartureProgramme::query()->where('automatic', true)->whereDate('ends_on', '>=', today())->chunkById(100, function ($programmes): void {
            foreach ($programmes as $programme) {
                DB::transaction(function () use ($programme): void {
                    $programme = DepartureProgramme::query()->lockForUpdate()->findOrFail($programme->id);
                    if (! $programme->automatic) {
                        return;
                    }
                    $active = $programme->runs()->where('operation', 'automatic')->whereIn('status', ['queued', 'running'])->first();
                    if ($active !== null) {
                        if ($active->updated_at?->lt(now()->subMinutes(5))) {
                            MaintainDepartureProgramme::dispatch($active->id)->afterCommit();
                        }

                        return;
                    }
                    $from = now()->toImmutable()->startOfDay()->max($programme->starts_on);
                    $until = now()->toImmutable()->startOfDay()->addDays($programme->horizon_days - 1)->min($programme->ends_on);
                    if ($from->gt($until)) {
                        return;
                    }
                    $run = $programme->runs()->create(['operation' => 'automatic', 'status' => 'queued', 'processed' => 0, 'result' => ['phase' => 'generate', 'from' => $from->toDateString(), 'next_date' => $from->toDateString(), 'until' => $until->toDateString(), 'errors' => []], 'created_by' => $programme->automatic_approved_by]);
                    MaintainDepartureProgramme::dispatch($run->id)->afterCommit();
                });
            }
        });

        return self::SUCCESS;
    }
}
