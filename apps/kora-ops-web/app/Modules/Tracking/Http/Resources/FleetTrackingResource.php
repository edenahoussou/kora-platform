<?php

namespace App\Modules\Tracking\Http\Resources;

use App\Models\User;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\FleetLite\Models\Incident;
use App\Modules\Tracking\Actions\ComputeRouteAdherenceAction;
use App\Modules\Tracking\Models\TrackingPosition;
use App\Modules\Tracking\Models\TrackingSession;
use App\Modules\Tracking\Services\FleetTrackingStateResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Contrat fleet/tracking : UUID, codes métier et données nécessaires à la
 * carte uniquement — aucun identifiant numérique interne (ticket MEZ-100,
 * critère d'acceptation "ne révèle aucun identifiant numérique interne").
 * Requiert `departure.line.stops`, `departure.driver`,
 * `departure.secondaryDriver`, `departure.mechanic`,
 * `departure.incidents.creator`, `bus.busModel`, `terminal.agency`,
 * `conductor` et `lastPosition` chargés (voir FleetTrackingQuery::build).
 *
 * @mixin TrackingSession
 */
class FleetTrackingResource extends JsonResource
{
    /**
     * @param  Collection<int, TrackingPosition>|null  $recentPositions
     */
    public function __construct(
        TrackingSession $resource,
        private readonly ?Carbon $terminalLastSeenAt,
        private readonly ?Collection $recentPositions = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resolver = app(FleetTrackingStateResolver::class);
        $departure = $this->departure;
        $line = $departure->line;
        $agency = $this->terminal->agency;

        // Ordre de circulation réel du départ — un retour circule dans
        // le sens inverse de la définition canonique de la ligne.
        $stops = $departure->tripStops();
        $geolocatedStops = $stops
            ->filter(fn (Stop $stop): bool => $stop->latitude !== null && $stop->longitude !== null)
            ->values();

        return [
            'session_uuid' => $this->uuid,
            'state' => $resolver->resolve($this->resource, $this->terminalLastSeenAt)->value,
            'departure' => [
                'uuid' => $departure->uuid,
                'line_code' => $line->code,
                'line_name' => $line->name,
                'origin' => $this->stopLabel($stops->first()),
                'destination' => $this->stopLabel($stops->last()),
                'date' => $departure->departure_date?->format('Y-m-d'),
                'time' => substr((string) $departure->departure_time, 0, 5),
                'status' => $departure->status->value,
            ],
            'bus' => [
                'uuid' => $this->bus->uuid,
                'registration_number' => $this->bus->registration_number,
                'model' => $this->busModelLabel(),
                'seat_count' => (int) $this->bus->seat_count,
            ],
            'terminal' => [
                'uuid' => $this->terminal->uuid,
                'type' => $this->terminal->type->value,
                'serial_number' => $this->terminal->serial_number,
            ],
            'conductor' => [
                'uuid' => $this->conductor->uuid,
                'name' => $this->conductor->name,
                'phone' => $this->conductor->phone,
            ],
            'position' => $this->last_position_at === null ? null : [
                'latitude' => $this->last_latitude,
                'longitude' => $this->last_longitude,
                'accuracy_meters' => $this->last_accuracy_meters,
                'speed_kmh' => $this->last_speed_kmh,
                'heading_degrees' => $this->last_heading_degrees,
                'quality' => $this->last_quality?->value,
                'recorded_at' => $this->last_position_at->toISOString(),
                'age_seconds' => $resolver->ageSeconds($this->resource),
                'source' => $this->lastPosition?->source?->value,
            ],
            'opened_at' => $this->opened_at->toISOString(),
            'agency' => $agency === null ? null : [
                'uuid' => $agency->uuid,
                'name' => $agency->name,
                'code' => $agency->code,
            ],
            'recent_positions' => ($this->recentPositions ?? collect())
                ->map(fn (TrackingPosition $position): array => [
                    'uuid' => $position->uuid,
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'speed_kmh' => $position->speed_kmh,
                    'heading_degrees' => $position->heading_degrees,
                    'accuracy_meters' => $position->accuracy_meters,
                    'recorded_at' => $position->recorded_at->toISOString(),
                    'source' => $position->source->value,
                ])
                ->values(),
            'crew' => [
                'driver' => $this->crewMember($departure->driver),
                'secondary_driver' => $this->crewMember($departure->secondaryDriver),
                'conductor' => $this->crewMember($departure->conductor),
                'mechanic' => $this->crewMember($departure->mechanic),
            ],
            'planned_stops' => $geolocatedStops
                ->map(fn (Stop $stop): array => [
                    'uuid' => $stop->uuid,
                    'name' => $stop->name,
                    'city' => $stop->city,
                    'order' => (int) $stop->stop_order,
                    'km_from_origin' => (float) $stop->km_from_origin,
                    'latitude' => (float) $stop->latitude,
                    'longitude' => (float) $stop->longitude,
                ])
                ->values(),
            'incidents' => $departure->incidents
                ->map(fn (Incident $incident): array => [
                    'uuid' => $incident->uuid,
                    'type' => $incident->type->value,
                    'status' => $incident->status->value,
                    'description' => $incident->description,
                    'latitude' => $incident->latitude,
                    'longitude' => $incident->longitude,
                    'occurred_at' => $incident->occurred_at?->toISOString(),
                    'reporter_name' => $incident->creator?->name,
                ])
                ->values(),
            'route_adherence' => app(ComputeRouteAdherenceAction::class)->execute(
                $this->last_latitude,
                $this->last_longitude,
                $geolocatedStops,
            ),
        ];
    }

    /**
     * Libellé lisible d'un arrêt extrémité : "Cotonou (Gare Centrale)".
     */
    private function stopLabel(?Stop $stop): ?string
    {
        if ($stop === null) {
            return null;
        }

        return $stop->city !== '' && $stop->city !== $stop->name
            ? "{$stop->city} ({$stop->name})"
            : $stop->name;
    }

    private function busModelLabel(): ?string
    {
        $label = trim(implode(' ', array_filter([$this->bus->brand, $this->bus->model])));

        return $label !== '' ? $label : $this->bus->busModel?->name;
    }

    /**
     * @return array{uuid: string, name: string, phone: string|null}|null
     */
    private function crewMember(?User $member): ?array
    {
        if ($member === null) {
            return null;
        }

        return [
            'uuid' => $member->uuid,
            'name' => $member->name,
            'phone' => $member->phone,
        ];
    }
}
