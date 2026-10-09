<?php

namespace App\Modules\Monitoring\Http\Controllers;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\Monitoring\Data\MonitoringPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Health\Facades\Health;
use Throwable;

use function Illuminate\Support\enum_value;

final class MonitoringController
{
    public function __invoke(Request $request): Response
    {
        $actor = $request->user();

        abort_unless($actor instanceof User && enum_value($actor->status) === UserStatus::Active->value, 403);

        Gate::forUser($actor)->authorize(MonitoringPermission::View->value);

        return Inertia::render('administration/monitoring', [
            'app' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'url' => config('app.url'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
            ],
            'checks' => $this->runChecks(),
            'logViewer' => [
                'apiBase' => url('/log-viewer/api'),
            ],
            'generatedAt' => now()->toISOString(),
        ]);
    }

    /**
     * Exécute les checks enregistrés et les sérialise pour l'écran.
     *
     * @return list<array{name: string, label: string, status: string, summary: string, message: string, meta: array<string, mixed>, endedAt: string|null}>
     */
    private function runChecks(): array
    {
        $checks = [];

        foreach (Health::registeredChecks() as $check) {
            if (! $check->shouldRun()) {
                continue;
            }

            try {
                $result = $check->run();
            } catch (Throwable) {
                $result = $check->markAsCrashed();
            }

            $checks[] = [
                'name' => $check->getName(),
                'label' => $check->getLabel(),
                'status' => (string) $result->status->value,
                'summary' => $result->getShortSummary(),
                'message' => $result->getNotificationMessage(),
                'meta' => $result->meta,
                'endedAt' => isset($result->ended_at) ? $result->ended_at->toIso8601String() : null,
            ];
        }

        return $checks;
    }
}
