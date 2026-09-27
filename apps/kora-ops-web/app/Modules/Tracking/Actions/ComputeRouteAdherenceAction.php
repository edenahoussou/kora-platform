<?php

namespace App\Modules\Tracking\Actions;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Settings\Settings\OperationsSettings;
use App\Modules\Tracking\Data\TrackingRouteAdherenceStatus;
use Illuminate\Support\Collection;

/**
 * Adhérence à l'itinéraire pour fleet/tracking : distance de la dernière
 * position connue à la polyligne reliant les arrêts géolocalisés de la
 * ligne (par `stop_order`).
 *
 * Les arrêts sont des waypoints espacés, pas la géométrie routière réelle :
 * le couloir de tolérance (`trackingRouteCorridorMeters`) doit rester
 * généreux pour ne pas signaler une déviation sur une simple courbe de
 * route entre deux arrêts.
 */
class ComputeRouteAdherenceAction
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * @param  Collection<int, Stop>  $stops  arrêts déjà triés par stop_order
     * @return array{status: string, distance_from_route_m: int|null}
     */
    public function execute(?float $latitude, ?float $longitude, Collection $stops): array
    {
        $waypoints = $stops
            ->filter(fn (Stop $stop): bool => $stop->latitude !== null && $stop->longitude !== null)
            ->map(fn (Stop $stop): array => [(float) $stop->latitude, (float) $stop->longitude])
            ->values();

        if ($waypoints->count() < 2) {
            return $this->result(TrackingRouteAdherenceStatus::Unavailable);
        }

        if ($latitude === null || $longitude === null) {
            return $this->result(TrackingRouteAdherenceStatus::InsufficientData);
        }

        $distance = PHP_FLOAT_MAX;

        for ($i = 0; $i < $waypoints->count() - 1; $i++) {
            $distance = min(
                $distance,
                $this->distanceToSegmentMeters($latitude, $longitude, $waypoints[$i], $waypoints[$i + 1]),
            );
        }

        $onRoute = $distance <= (float) app(OperationsSettings::class)->trackingRouteCorridorMeters;

        return $this->result(
            $onRoute ? TrackingRouteAdherenceStatus::OnRoute : TrackingRouteAdherenceStatus::Deviation,
            (int) round($distance),
        );
    }

    /**
     * Distance point → segment en mètres, en projection équirectangulaire
     * locale centrée sur le point (précise à l'échelle d'une ligne Bus).
     *
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private function distanceToSegmentMeters(float $lat, float $lng, array $a, array $b): float
    {
        $cosLat = cos(deg2rad($lat));
        $toX = fn (array $point): float => deg2rad($point[1] - $lng) * $cosLat * self::EARTH_RADIUS_METERS;
        $toY = fn (array $point): float => deg2rad($point[0] - $lat) * self::EARTH_RADIUS_METERS;

        $ax = $toX($a);
        $ay = $toY($a);
        $dx = $toX($b) - $ax;
        $dy = $toY($b) - $ay;
        $lengthSquared = $dx * $dx + $dy * $dy;

        // Segment dégénéré (deux arrêts confondus) : distance au point A.
        if ($lengthSquared === 0.0) {
            return hypot($ax, $ay);
        }

        // Le bus est l'origine du repère : t = -A·AB / |AB|², borné à [0, 1].
        $t = max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $lengthSquared));

        return hypot($ax + $t * $dx, $ay + $t * $dy);
    }

    /**
     * @return array{status: string, distance_from_route_m: int|null}
     */
    private function result(TrackingRouteAdherenceStatus $status, ?int $distance = null): array
    {
        return ['status' => $status->value, 'distance_from_route_m' => $distance];
    }
}
