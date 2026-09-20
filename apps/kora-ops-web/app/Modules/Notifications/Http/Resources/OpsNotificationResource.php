<?php

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\OpsNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrat unique des notifications (OPS Mobile et OPS Web) : UUID, codes
 * métier et messages uniquement. Aucun identifiant numérique, aucune donnée
 * financière. Requiert la relation `event` (avec `departure.line` et
 * `terminal`) chargée.
 *
 * @mixin OpsNotification
 */
class OpsNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->event;
        $departure = $event->departure;
        $terminal = $event->terminal;

        return [
            'uuid' => $this->uuid,
            'event_uuid' => $event->uuid,
            'type' => $event->type->value,
            'severity' => $event->severity->value,
            'title' => $event->title,
            'message' => $event->message,
            'action' => $event->type->action()->value,
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'occurred_at' => $event->occurred_at->toISOString(),
            'departure' => $departure === null ? null : [
                'uuid' => $departure->uuid,
                'line_code' => $departure->line->code ?? null,
                'date' => $departure->departure_date?->format('Y-m-d'),
                'time' => substr((string) $departure->departure_time, 0, 5),
            ],
            'terminal' => $terminal === null ? null : [
                'uuid' => $terminal->uuid,
                'serial_number' => $terminal->serial_number,
            ],
            'data' => $event->data ?? [],
        ];
    }
}
