<?php

namespace App\Modules\Profitability\Services;

use App\Modules\Departures\Models\Departure;
use App\Modules\Settings\Settings\OperationsSettings;
use App\Modules\Tracking\Data\TrackingPositionStatus;
use App\Modules\Tracking\Models\TrackingPosition;
use App\Modules\Tracking\Models\TrackingSession;
use Carbon\CarbonImmutable;

/**
 * Distance réelle par départ à partir des positions GPS, avec repli sur la
 * distance planifiée et badge « estimé » quand la couverture est insuffisante.
 */
class GpsDistanceCalculator
{
    /**
     * @param  list<int>  $departureIds
     * @return array<int, array{distanceKm: float, gpsDistanceKm: float, plannedDistanceKm: float, coveragePercent: float, source: string, hasSession: bool}>
     */
    public function forDepartures(array $departureIds): array
    {
        if ($departureIds === []) {
            return [];
        }

        $settings = app(OperationsSettings::class);
        $departures = Departure::query()
            ->withTrashed()
            ->with(['line' => fn ($query) => $query->withTrashed(), 'line.stops'])
            ->whereIn('id', $departureIds)
            ->get()
            ->keyBy('id');

        $sessions = TrackingSession::query()
            ->whereIn('departure_id', $departureIds)
            ->get(['id', 'departure_id', 'opened_at', 'closed_at']);

        $positions = $sessions->isEmpty()
            ? collect()
            : TrackingPosition::query()
                ->whereIn('tracking_session_id', $sessions->pluck('id')->all())
                ->where('status', TrackingPositionStatus::Fix->value)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('tracking_session_id')
                ->orderBy('recorded_at')
                ->orderBy('sequence')
                ->get(['tracking_session_id', 'recorded_at', 'latitude', 'longitude', 'accuracy_meters']);

        $positionsBySession = $positions->groupBy('tracking_session_id');
        $now = CarbonImmutable::now('UTC');

        $results = [];

        foreach ($departureIds as $departureId) {
            $departure = $departures->get($departureId);
            $departureSessions = $sessions->where('departure_id', $departureId);
            $gpsDistance = 0.0;
            $coveredSeconds = 0.0;
            $sessionSeconds = 0.0;

            foreach ($departureSessions as $session) {
                $openedAt = CarbonImmutable::instance($session->opened_at);
                $closedAt = $session->closed_at === null ? $now : CarbonImmutable::instance($session->closed_at);
                $sessionSeconds += max(1.0, (float) $openedAt->diffInSeconds($closedAt));

                $fixes = $positionsBySession->get($session->getKey(), collect())
                    ->filter(fn (TrackingPosition $position): bool => $position->accuracy_meters === null
                        || (float) $position->accuracy_meters <= (float) $settings->trackingDegradedAccuracyMeters)
                    ->values();

                for ($index = 1; $index < $fixes->count(); $index++) {
                    $previous = $fixes[$index - 1];
                    $current = $fixes[$index];
                    $gapSeconds = (float) CarbonImmutable::instance($previous->recorded_at)->diffInSeconds($current->recorded_at);

                    if ($gapSeconds <= 0 || $gapSeconds > $settings->profitabilityGpsMaxGapMinutes * 60) {
                        continue;
                    }

                    $segmentKm = $this->haversineKm(
                        (float) $previous->latitude,
                        (float) $previous->longitude,
                        (float) $current->latitude,
                        (float) $current->longitude,
                    );

                    if (($segmentKm / ($gapSeconds / 3600)) > $settings->profitabilityGpsMaxSpeedKmh) {
                        continue;
                    }

                    $gpsDistance += $segmentKm;
                    $coveredSeconds += $gapSeconds;
                }
            }

            $coverage = $sessionSeconds > 0
                ? min(100.0, round($coveredSeconds / $sessionSeconds * 100, 1))
                : 0.0;
            $plannedDistance = $this->plannedDistanceKm($departure);
            $useGps = $coverage >= $settings->profitabilityGpsMinCoveragePercent && $gpsDistance > 0;

            $results[$departureId] = [
                'distanceKm' => round($useGps ? $gpsDistance : $plannedDistance, 3),
                'gpsDistanceKm' => round($gpsDistance, 3),
                'plannedDistanceKm' => round($plannedDistance, 3),
                'coveragePercent' => $coverage,
                'source' => $useGps ? 'gps' : 'estimated',
                'hasSession' => $departureSessions->isNotEmpty(),
            ];
        }

        return $results;
    }

    private function plannedDistanceKm(?Departure $departure): float
    {
        if ($departure === null) {
            return 0.0;
        }

        $lineDistance = (float) ($departure->line->distance_km ?? 0);
        if ($lineDistance > 0) {
            return $lineDistance;
        }

        $stops = $departure->line->stops ?? collect();
        if ($stops->isEmpty()) {
            return 0.0;
        }

        $kmValues = $stops->map(fn ($stop): float => (float) $stop->km_from_origin);

        return max(0.0, (float) $kmValues->max() - (float) $kmValues->min());
    }

    private function haversineKm(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadiusKm = 6371.0;
        $deltaLatitude = deg2rad($latitudeB - $latitudeA);
        $deltaLongitude = deg2rad($longitudeB - $longitudeA);
        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($deltaLongitude / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
