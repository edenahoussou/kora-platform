<?php

namespace App\Jobs;

use App\Models\User;
use App\Modules\Departures\Models\PlannedDeparture;
use App\Modules\Departures\Models\ProgrammingRun;
use App\Modules\Departures\Services\PlanningService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class MaintainDepartureProgramme implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $runId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('programming-run-'.$this->runId))->releaseAfter(10)->expireAfter(80)];
    }

    public function handle(PlanningService $planning): void
    {
        $run = ProgrammingRun::query()->with('programme')->findOrFail($this->runId);
        if (in_array($run->status, ['completed', 'partial', 'paused', 'failed'], true)) {
            return;
        }
        $programme = $run->programme;
        $actor = User::query()->find($run->created_by);
        if (! $programme->automatic || $actor === null || $actor->getRawOriginal('status') !== 'active' || ! $actor->can('departures.manage')) {
            $run->update(['status' => 'paused', 'result' => $run->result + ['message' => 'Automatisation en pause ou autorisation retirée.']]);

            return;
        }
        $cursor = CarbonImmutable::parse($run->result['next_date']);
        $until = CarbonImmutable::parse($run->result['until']);
        $phase = $run->result['phase'] ?? 'generate';
        $run->update(['status' => 'running']);
        $result = ['processed' => 0, 'errors' => []];
        if ($phase === 'generate') {
            $planning->generate($programme, $cursor, $cursor, $actor);
        } else {
            $items = $programme->occurrences()->whereDate('service_date', $cursor)->whereNull('departure_id')->where('state', '!=', 'excluded')->get();
            $result = $planning->mutate(array_values($items->map(fn (PlannedDeparture $item): array => ['uuid' => $item->uuid, 'revision' => $item->revision])->all()), 'publish', [], $actor, CarbonImmutable::parse($run->result['from']), $until);
        }
        $lastDay = $cursor->gte($until);
        $finished = $lastDay && $phase === 'publish';
        $errors = array_replace($run->result['errors'] ?? [], $result['errors']);
        $next = $cursor->addDay()->toDateString();
        if ($lastDay && $phase === 'generate') {
            $phase = 'publish';
            $next = $run->result['from'];
        }
        $run->update([
            'processed' => $run->processed + $result['processed'],
            'status' => $finished ? ($errors === [] ? 'completed' : 'partial') : 'queued',
            'result' => ['phase' => $phase, 'from' => $run->result['from'], 'next_date' => $next, 'until' => $until->toDateString(), 'errors' => $errors],
        ]);
        if (! $finished) {
            self::dispatch($run->id)->delay(now()->addSecond());
        }
    }

    public function failed(?Throwable $exception): void
    {
        ProgrammingRun::query()->whereKey($this->runId)->update(['status' => 'failed']);
        report($exception ?? new \RuntimeException('Échec de programmation automatique.'));
    }
}
