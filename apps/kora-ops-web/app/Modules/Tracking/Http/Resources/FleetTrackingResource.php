<?php

namespace App\Modules\Tracking\Http\Resources;

use App\Modules\Tracking\Models\TrackingSession;
use App\Modules\Tracking\Services\FleetTrackingStateResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Contrat fleet/tracking : UUID, codes métier et données nécessaires à la
 * carte uniquement — aucun identifiant numérique interne (ticket MEZ-100,
 * critère d'acceptation "ne révèle aucun identifiant numérique interne").
 * Requiert `departure.line`, `bus`, `terminal` et `conductor` chargés.
 *
 * @mixin TrackingSession
 */
class FleetTrackingResource extends JsonResource
{
    public function __construct(TrackingSession $resource, private readonly ?Carbon $terminalLastSeenAt)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $departure = $this->departure;
        $state = app(FleetTrackingStateResolver::class)->resolve($this->resource, $this->terminalLastSeenAt);

        return [
            'session_uuid' => $this->uuid,
            'state' => $state->value,
            'departure' => [
                'uuid' => $departure->uuid,
                'line_code' => $departure->line->code ?? null,
                'date' => $departure->departure_date?->format('Y-m-d'),
                'time' => substr((string) $departure->departure_time, 0, 5),
                'status' => $departure->status->value,
            ],
            'bus' => [
                'uuid' => $this->bus->uuid,
                'registration_number' => $this->bus->registration_number,
            ],
            'terminal' => [
                'uuid' => $this->terminal->uuid,
                'type' => $this->terminal->type->value,
            ],
            'conductor' => [
                'uuid' => $this->conductor->uuid,
                'name' => $this->conductor->name,
            ],
            'position' => $this->last_position_at === null ? null : [
                'latitude' => $this->last_latitude,
                'longitude' => $this->last_longitude,
                'accuracy_meters' => $this->last_accuracy_meters,
                'speed_kmh' => $this->last_speed_kmh,
                'heading_degrees' => $this->last_heading_degrees,
                'quality' => $this->last_quality?->value,
                'recorded_at' => $this->last_position_at->toISOString(),
                'age_seconds' => app(FleetTrackingStateResolver::class)->ageSeconds($this->resource),
            ],
            'opened_at' => $this->opened_at->toISOString(),
        ];
    }
}
