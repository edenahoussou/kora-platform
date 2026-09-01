<?php

namespace App\Jobs;

use App\Models\User;
use App\Modules\Departures\Models\ProgrammingRun;
use App\Modules\Departures\Services\PlanningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessProgrammingSelection implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $runId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('programming-selection-'.$this->runId))->releaseAfter(10)->expireAfter(80)];
    }

    public function handle(PlanningService $planning): void
    {
        DB::transaction(function () use ($planning): void {
            $run = ProgrammingRun::query()->lockForUpdate()->findOrFail($this->runId);
            if (! in_array($run->status, ['queued', 'running'], true)) {
                return;
            }
            $actor = User::query()->find($run->created_by);
            if ($actor === null || $actor->getRawOriginal('status') !== 'active' || ! $actor->can('departures.manage') || ($run->operation === 'exclude' && ! $actor->can('departures.cancel'))) {
                $run->update(['status' => 'paused', 'result' => $run->result + ['message' => 'Autorisation retirée avant exécution.']]);

                return;
            }
            $offset = $run->result['offset'] ?? 0;
            $selection = array_values(array_slice($run->result['selection'], $offset, 25));
            $result = $planning->mutate($selection, $run->operation, $run->result['changes'], $actor);
            $offset += count($selection);
            $errors = array_replace($run->result['errors'] ?? [], $result['errors']);
            $finished = $offset >= count($run->result['selection']);
            $run->update([
                'processed' => $run->processed + $result['processed'],
                'status' => $finished ? ($errors === [] ? 'completed' : 'partial') : 'queued',
                'result' => array_replace($run->result, ['offset' => $offset, 'errors' => $errors]),
            ]);
            if (! $finished) {
                self::dispatch($run->id)->afterCommit();
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        ProgrammingRun::query()->whereKey($this->runId)->update(['status' => 'failed']);
        if ($exception !== null) {
            report($exception);
        }
    }
}
