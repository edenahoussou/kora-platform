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
        $payload = $this->data ?? [];
        $event = $this->event;
        $departure = $event?->departure;
        $terminal = $event?->terminal;
        $user = $request->user();
        $actionUrl = null;
        $type = $event === null ? (string) ($payload['type'] ?? 'fiscal_credit_note') : $event->type->value;
        $severity = $event === null ? (string) ($payload['severity'] ?? 'info') : $event->severity->value;
        $action = $event === null ? (string) ($payload['action'] ?? 'none') : $event->type->action()->value;

        if ($departure !== null && $user?->can('departures.view')) {
            $actionUrl = route('departures.show', $departure->uuid);
        } elseif (str_starts_with($type, 'fiscalization_') && $user?->can('fiscalization.view')) {
            $actionUrl = route('fiscal-invoices.index');
        } elseif ($type === 'fiscal_credit_note' && $user?->can('fiscalization.credit')) {
            $actionUrl = route('fiscal-invoices.index');
        } elseif (str_starts_with($type, 'cash_') && $user?->can('cash.approve-variance')) {
            $actionUrl = route('cash');
        } elseif (str_starts_with($type, 'assisted_booking_') && ($user?->can('assisted-bookings.view-network') || $user?->can('assisted-bookings.view-agency'))) {
            $actionUrl = route('assisted-bookings.index');
        }

        return [
            'uuid' => $this->uuid,
            'event_uuid' => $event === null ? (string) ($payload['event_uuid'] ?? $this->uuid) : $event->uuid,
            'type' => $type,
            'severity' => $severity,
            'title' => $event === null ? (string) ($payload['title'] ?? 'Mise à jour') : $event->title,
            'message' => $event === null ? (string) ($payload['message'] ?? '') : $event->message,
            'action' => $action,
            'action_url' => $actionUrl,
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'occurred_at' => $event === null ? (string) ($payload['occurred_at'] ?? $this->created_at?->toISOString()) : $event->occurred_at->toISOString(),
            'departure' => $departure === null ? null : [
                'uuid' => $departure->uuid,
                'line_code' => $departure->line->code,
                'date' => $departure->departure_date?->format('Y-m-d'),
                'time' => substr((string) $departure->departure_time, 0, 5),
            ],
            'terminal' => $terminal === null ? null : [
                'uuid' => $terminal->uuid,
                'serial_number' => $terminal->serial_number,
            ],
            'data' => $event === null ? (array) ($payload['data'] ?? []) : ($event->data ?? []),
        ];
    }
}
