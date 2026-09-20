<?php

namespace App\Modules\FleetLite\Http\Resources;

use App\Modules\FleetLite\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrat OPS Mobile d'un incident : UUID, codes et messages uniquement —
 * aucun identifiant numérique. Requiert `departure.line` chargé pour le bloc
 * `departure`.
 *
 * @mixin Incident
 */
class OpsIncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $departure = $this->departure;

        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'description' => $this->description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'departure' => $departure === null ? null : [
                'uuid' => $departure->uuid,
                'line_code' => $departure->line->code ?? null,
                'date' => $departure->departure_date?->format('Y-m-d'),
                'time' => substr((string) $departure->departure_time, 0, 5),
            ],
        ];
    }
}
