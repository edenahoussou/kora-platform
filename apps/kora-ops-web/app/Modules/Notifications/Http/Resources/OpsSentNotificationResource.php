<?php

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\OpsNotificationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un événement que l'utilisateur a lui-même déclenché (incident signalé,
 * départ fait progresser…) — page « Notifications envoyées » (Profil).
 *
 * Même forme que `OpsNotificationResource` pour que le mobile réutilise le
 * même DTO/la même carte sans distinction de type, avec deux différences
 * volontaires : `uuid`/`event_uuid` sont ici les deux l'UUID de
 * l'événement (il n'y a pas de ligne `ops_notifications` par destinataire
 * à exposer, l'utilisateur n'est pas destinataire de son propre envoi) et
 * `is_read`/`read_at` n'ont pas de sens ici — figés à "lu" pour ne pas
 * afficher le repère visuel "non lue" côté client.
 *
 * @mixin OpsNotificationEvent
 */
class OpsSentNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $departure = $this->departure;
        $terminal = $this->terminal;

        return [
            'uuid' => $this->uuid,
            'event_uuid' => $this->uuid,
            'type' => $this->type->value,
            'severity' => $this->severity->value,
            'title' => $this->title,
            'message' => $this->message,
            'action' => $this->type->action()->value,
            'is_read' => true,
            'read_at' => null,
            'created_at' => $this->created_at?->toISOString(),
            'occurred_at' => $this->occurred_at->toISOString(),
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
            'data' => $this->data ?? [],
        ];
    }
}
