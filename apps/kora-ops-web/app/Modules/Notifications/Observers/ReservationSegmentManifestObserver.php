<?php

namespace App\Modules\Notifications\Observers;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Models\Departure;
use App\Modules\Notifications\Jobs\PublishManifestStaleNotificationJob;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Détecte les changements qui rendent le manifeste d'un départ périmé côté
 * terminal : billet émis, annulé, siège ou arrêts déplacés. Les changements
 * produits par le terrain lui-même (embarquement, libération, no-show) sont
 * ignorés : le mobile les connaît déjà.
 *
 * Aucune requête de notification quand le départ n'a aucun équipage.
 */
class ReservationSegmentManifestObserver
{
    public function saved(ReservationSegment $segment): void
    {
        try {
            // ✅ CORRECTION 3 : departure_id est typé comme int<0, max> par PHPStan,
            // il ne peut donc jamais être null. On utilise ! pour vérifier s'il est falsy (ex: 0).
            if (! $segment->departure_id || ! $this->makesManifestStale($segment)) {
                return;
            }

            $hasCrew = Departure::query()
                ->whereKey($segment->departure_id)
                ->where(function (Builder $query): void {
                    $query->whereNotNull('driver_id')
                        ->orWhereNotNull('secondary_driver_id')
                        ->orWhereNotNull('conductor_id')
                        ->orWhereNotNull('mechanic_id');
                })
                ->exists();

            if (! $hasCrew) {
                return;
            }

            $departureId = $segment->departure_id;

            DB::afterCommit(function () use ($departureId): void {
                try {
                    PublishManifestStaleNotificationJob::dispatch($departureId)
                        ->delay(now()->addSeconds(app(OperationsSettings::class)->manifestStaleDebounceSeconds));
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function makesManifestStale(ReservationSegment $segment): bool
    {
        if ($segment->wasRecentlyCreated) {
            return $segment->status === ReservationSegmentStatus::Ticketed;
        }

        if ($segment->wasChanged('status') && in_array($segment->status, [ReservationSegmentStatus::Ticketed, ReservationSegmentStatus::Cancelled], true)) {
            return true;
        }

        return $segment->wasChanged(['seat_id', 'origin_stop_id', 'destination_stop_id']);
    }
}
