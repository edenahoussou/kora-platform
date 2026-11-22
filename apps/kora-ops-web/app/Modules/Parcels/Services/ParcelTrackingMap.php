<?php

namespace App\Modules\Parcels\Services;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Parcels\Data\ParcelEventKind;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelAssignment;
use App\Modules\Parcels\Models\ParcelEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Construit le suivi cartographique interne d'un colis.
 *
 * La charge retournée est destinée aux utilisateurs OPS habilités
 * (`parcels.view`). Elle ne contient aucune PII, aucun code de retrait,
 * aucun hash et aucun payload fournisseur : uniquement des arrêts,
 * villes, horodatages, libellés d'événements et la position du bus.
 */
class ParcelTrackingMap
{
    /** @return array<string, mixed> */
    public function build(Parcel $parcel): array
    {
        $parcel->loadMissing([
            'corridor.originHub.stop',
            'corridor.destinationHub.stop',
            'events.hub.stop',
            'assignments.departure.bus',
            'assignments.departure.line.stops',
            'assignments.departure.latestTrackingSession',
            'incidents',
        ]);

        $events = $parcel->events
            ->sortBy(fn (ParcelEvent $event): int => $event->observed_at->getTimestamp())
            ->values();

        $departure = $this->departure($parcel);
        $stops = $this->tripStops($parcel, $departure);
        $planned = $this->stopPoints($stops);
        $eventPoints = $this->eventPoints($events);
        $vehicle = $this->vehiclePayload($departure);
        $current = $this->currentPoint($vehicle, $eventPoints);
        $eventProgress = $this->eventProgress($stops, $events, (string) $parcel->status);
        [$traveled, $progress] = $this->traveledPoints($planned, $eventPoints, $current, $eventProgress);

        return [
            'reference' => $parcel->reference,
            'status' => $parcel->status,
            'origin' => $parcel->corridor?->originHub?->stop?->city,
            'destination' => $parcel->corridor?->destinationHub?->stop?->city,
            'weight_grams' => $parcel->weight_grams,
            'eta' => $this->eta($parcel),
            'withdraw_by' => $parcel->return_due_at?->toIso8601String(),
            'last_event_at' => $events->last()?->observed_at?->toIso8601String(),
            'incident_open' => $parcel->incidents->contains(fn ($incident): bool => $incident->resolved_at === null),
            'planned' => $planned,
            'traveled' => $traveled,
            'current' => $current,
            'progress_ratio' => $progress,
            'events' => $events->map(fn (ParcelEvent $event): array => [
                'kind' => $event->kind,
                'observed_at' => $event->observed_at->toIso8601String(),
                'city' => $event->hub?->stop?->city,
                'hub_name' => $event->hub?->stop?->name,
                'public' => ParcelEventKind::isPublic($event->kind),
            ])->all(),
            'vehicle' => $vehicle,
        ];
    }

    private function departure(Parcel $parcel): ?Departure
    {
        $assignment = $parcel->assignments
            ->sortByDesc('id')
            ->first(fn (ParcelAssignment $candidate): bool => $candidate->departure !== null);

        return $assignment?->departure;
    }

    /**
     * Arrêts du départ dans l'ordre de circulation, limités entre l'arrêt
     * d'origine et l'arrêt de destination du corridor.
     *
     * @return Collection<int, Stop>
     */
    private function tripStops(Parcel $parcel, ?Departure $departure): Collection
    {
        $origin = $parcel->corridor?->originHub?->stop;
        $destination = $parcel->corridor?->destinationHub?->stop;

        if ($departure !== null) {
            $stops = $this->sliceTripStops($departure->tripStops(), $origin, $destination);

            if ($stops->isNotEmpty()) {
                return $stops;
            }
        }

        return collect([$origin, $destination])->filter()->values();
    }

    /**
     * @param  Collection<int, Stop>  $stops
     * @return Collection<int, Stop>
     */
    private function sliceTripStops(Collection $stops, ?Stop $origin, ?Stop $destination): Collection
    {
        if ($origin === null || $destination === null) {
            return $stops->values();
        }

        $originIndex = $stops->search(fn (Stop $stop): bool => $stop->id === $origin->id);
        $destinationIndex = $stops->search(fn (Stop $stop): bool => $stop->id === $destination->id);

        if ($originIndex === false || $destinationIndex === false) {
            return $stops->values();
        }

        $slice = $stops->slice(min($originIndex, $destinationIndex), abs($destinationIndex - $originIndex) + 1)->values();

        return $originIndex <= $destinationIndex ? $slice : $slice->reverse()->values();
    }

    /**
     * @param  Collection<int, Stop>  $stops
     * @return list<array<string, mixed>>
     */
    private function stopPoints(Collection $stops): array
    {
        $points = [];
        $seen = [];

        foreach ($stops->values() as $index => $stop) {
            $point = $this->stopPoint($stop, $index === 0 ? 'origin' : ($index === $stops->count() - 1 ? 'destination' : 'waypoint'));

            if ($point === null) {
                continue;
            }

            $key = $this->pointKey($point);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $points[] = $point;
        }

        return $points;
    }

    /**
     * @param  Collection<int, ParcelEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function eventPoints(Collection $events): array
    {
        $points = [];
        $seen = [];

        foreach ($events as $event) {
            $point = $this->stopPoint($event->hub?->stop, 'event', $event->kind, $event->observed_at);

            if ($point === null) {
                continue;
            }

            $key = $this->pointKey($point).'|'.$event->kind;

            if ($point['latitude'] === null || $point['longitude'] === null) {
                continue;
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $points[] = $point;
        }

        return $points;
    }

    /** @return array<string, mixed>|null */
    private function stopPoint(?Stop $stop, string $role, ?string $kind = null, ?CarbonInterface $observedAt = null): ?array
    {
        if ($stop === null) {
            return null;
        }

        return [
            'name' => $stop->name,
            'city' => $stop->city,
            'country' => $stop->country,
            'latitude' => $stop->latitude !== null ? (float) $stop->latitude : null,
            'longitude' => $stop->longitude !== null ? (float) $stop->longitude : null,
            'role' => $role,
            'kind' => $kind,
            'observed_at' => $observedAt?->toIso8601String(),
            'fresh' => null,
        ];
    }

    /** @param array<string, mixed> $point */
    private function pointKey(array $point): string
    {
        $latitude = $point['latitude'] !== null ? number_format((float) $point['latitude'], 5, '.', '') : 'null';
        $longitude = $point['longitude'] !== null ? number_format((float) $point['longitude'], 5, '.', '') : 'null';

        return mb_strtolower((string) $point['city']).'|'.$latitude.'|'.$longitude;
    }

    /**
     * Progression calculée sur les étapes nommées, même sans coordonnées GPS :
     * le dernier événement rattaché à un arrêt du parcours fixe l'avancement.
     *
     * @param  Collection<int, Stop>  $stops
     * @param  Collection<int, ParcelEvent>  $events
     */
    private function eventProgress(Collection $stops, Collection $events, string $status): float
    {
        if ($stops->count() < 2) {
            return 0.0;
        }

        $maxIndex = 0;

        foreach ($events as $event) {
            $hubStop = $event->hub?->stop;

            if ($hubStop === null) {
                continue;
            }

            $index = $stops->search(fn (Stop $stop): bool => $stop->id === $hubStop->id);

            if ($index === false) {
                $index = $stops->search(
                    fn (Stop $stop): bool => mb_strtolower((string) $stop->city) === mb_strtolower((string) $hubStop->city),
                );
            }

            if ($index !== false) {
                $maxIndex = max($maxIndex, (int) $index);
            }
        }

        if (in_array($status, ['withdrawn', 'returned', 'return_handed_over'], true)) {
            $maxIndex = $stops->count() - 1;
        }

        return min(1.0, $maxIndex / ($stops->count() - 1));
    }

    /**
     * @param  list<array<string, mixed>>  $planned
     * @param  list<array<string, mixed>>  $eventPoints
     * @param  array<string, mixed>|null  $current
     * @return array{0: list<array<string, mixed>>, 1: float}
     */
    private function traveledPoints(array $planned, array $eventPoints, ?array $current, float $eventProgress): array
    {
        if ($planned === []) {
            return [[], round($eventProgress, 4)];
        }

        $geolocated = array_values(array_filter(
            $planned,
            fn (array $point): bool => $point['latitude'] !== null && $point['longitude'] !== null,
        ));

        if ($geolocated === []) {
            return [[], round($eventProgress, 4)];
        }

        $maxIndex = 0;

        foreach ($eventPoints as $point) {
            $maxIndex = max($maxIndex, $this->nearestIndex($geolocated, $point));
        }

        if ($current !== null) {
            $maxIndex = max($maxIndex, $this->nearestIndex($geolocated, $current));
        }

        $traveled = array_slice($geolocated, 0, $maxIndex + 1);

        if ($current !== null && ! $this->samePoint($traveled[array_key_last($traveled)] ?? null, $current)) {
            $traveled[] = $current;
        }

        $ratio = max(
            $eventProgress,
            count($geolocated) > 1 ? min(1.0, $maxIndex / (count($geolocated) - 1)) : 0.0,
        );

        return [$traveled, round($ratio, 4)];
    }

    /**
     * @param  array<string, mixed>|null  $vehicle
     * @param  list<array<string, mixed>>  $eventPoints
     * @return array<string, mixed>|null
     */
    private function currentPoint(?array $vehicle, array $eventPoints): ?array
    {
        $position = $vehicle['position'] ?? null;

        if (is_array($position) && $position['latitude'] !== null && $position['longitude'] !== null) {
            return [
                'name' => $vehicle['registration_number'] ?? 'Bus',
                'city' => null,
                'country' => null,
                'latitude' => (float) $position['latitude'],
                'longitude' => (float) $position['longitude'],
                'role' => 'current',
                'kind' => 'vehicle',
                'observed_at' => $position['recorded_at'],
                'fresh' => $position['fresh'],
            ];
        }

        return $eventPoints === [] ? null : $eventPoints[array_key_last($eventPoints)];
    }

    /** @return array<string, mixed>|null */
    private function vehiclePayload(?Departure $departure): ?array
    {
        if ($departure === null) {
            return null;
        }

        $session = $departure->latestTrackingSession;
        $position = null;

        if ($session !== null && $session->last_latitude !== null && $session->last_longitude !== null && $session->last_position_at !== null) {
            $age = (int) $session->last_position_at->diffInSeconds(now(), true);
            $position = [
                'latitude' => (float) $session->last_latitude,
                'longitude' => (float) $session->last_longitude,
                'speed_kmh' => $session->last_speed_kmh !== null ? (float) $session->last_speed_kmh : null,
                'heading_degrees' => $session->last_heading_degrees !== null ? (float) $session->last_heading_degrees : null,
                'recorded_at' => $session->last_position_at->toIso8601String(),
                'age_seconds' => $age,
                'fresh' => $age <= 900,
            ];
        }

        return [
            'registration_number' => $departure->bus?->registration_number,
            'line_code' => $departure->line?->code,
            'line_name' => $departure->line?->name,
            'departure_at' => $departure->scheduledDepartureAt()->toIso8601String(),
            'status' => $departure->status->value,
            'position' => $position,
        ];
    }

    private function eta(Parcel $parcel): ?string
    {
        $assignment = $parcel->assignments->sortByDesc('id')->first(fn (ParcelAssignment $candidate): bool => $candidate->departure !== null);
        $departure = $assignment?->departure;

        return $assignment?->unloaded_at?->toIso8601String()
            ?? $departure?->estimated_arrival_at?->copy()->addMinutes($departure->delay_minutes)->toIso8601String();
    }

    /**
     * @param  list<array<string, mixed>>  $planned
     * @param  array<string, mixed>  $point
     */
    private function nearestIndex(array $planned, array $point): int
    {
        $bestIndex = 0;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($planned as $index => $candidate) {
            $distance = $this->squaredDistance($candidate, $point);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestIndex = $index;
            }
        }

        return $bestIndex;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function squaredDistance(array $left, array $right): float
    {
        $lat = ((float) $left['latitude']) - ((float) $right['latitude']);
        $lng = ((float) $left['longitude']) - ((float) $right['longitude']);

        return $lat * $lat + $lng * $lng;
    }

    /**
     * @param  array<string, mixed>|null  $left
     * @param  array<string, mixed>  $right
     */
    private function samePoint(?array $left, array $right): bool
    {
        return $left !== null
            && $this->squaredDistance($left, $right) < 0.0000000001;
    }
}
