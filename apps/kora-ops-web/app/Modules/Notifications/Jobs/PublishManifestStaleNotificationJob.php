<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Departures\Actions\ComputeManifestVersionAction;
use App\Modules\Departures\Actions\ResolveManifestSegmentsAction;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Notifications\Services\OpsNotifier;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Publie « manifeste périmé » pour un départ dont un billet vient d'être
 * émis, annulé ou déplacé. Différé et unique par départ : une rafale de
 * ventes ne produit qu'une notification, identifiée par la version du
 * manifeste (`manifest_stale:{départ}:{version}`).
 */
class PublishManifestStaleNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $departureId) {}

    public function uniqueId(): string
    {
        return (string) $this->departureId;
    }

    public function uniqueFor(): int
    {
        return app(OperationsSettings::class)->manifestStaleDebounceSeconds + 60;
    }

    public function handle(
        ResolveManifestSegmentsAction $resolveSegments,
        ComputeManifestVersionAction $computeVersion,
        OpsNotifier $notifier,
    ): void {
        try {
            $departure = Departure::query()->with('line')->find($this->departureId);

            if ($departure === null || in_array($departure->status, [DepartureStatus::Cancelled, DepartureStatus::Arrived], true)) {
                return;
            }

            [$segments, $tickets] = $resolveSegments->execute($departure);

            $notifier->manifestStale($departure, $computeVersion->execute($departure, $segments, $tickets));
        } catch (Throwable $exception) {
            // Une notification manquée ne doit jamais bloquer le flux de vente.
            report($exception);
        }
    }
}
