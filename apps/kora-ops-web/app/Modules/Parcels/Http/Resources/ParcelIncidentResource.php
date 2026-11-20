<?php

namespace App\Modules\Parcels\Http\Resources;

use App\Models\User;
use App\Modules\Parcels\Models\ParcelEvent;
use App\Modules\Parcels\Models\ParcelIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ParcelIncident */
class ParcelIncidentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $incident = $this->resource;
        $parcel = $incident->parcel;

        $timeline = $parcel->events
            ->filter(fn (ParcelEvent $event): bool => ($event->details['incident_uuid'] ?? null) === $incident->uuid)
            ->sortBy('observed_at')
            ->values()
            ->map(fn (ParcelEvent $event): array => [
                'kind' => $event->kind,
                'observed_at' => $event->observed_at->toIso8601String(),
                'actor' => $event->actor?->name,
                'reason' => $event->details['reason'] ?? null,
            ]);

        return [
            'uuid' => $incident->uuid,
            'kind' => $incident->kind,
            'description' => $incident->description,
            'blocking' => $incident->blocking,
            'status' => $incident->resolved_at === null ? 'open' : 'resolved',
            'opened_at' => $incident->created_at?->toIso8601String(),
            'opened_by' => $this->user($incident->openedBy),
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
            'resolved_by' => $this->user($incident->resolvedBy),
            'resolution_reason' => $incident->resolution_reason,
            'timeline' => $timeline,
        ];
    }

    /** @return array{uuid: string, name: string}|null */
    private function user(?User $user): ?array
    {
        return $user === null ? null : ['uuid' => $user->uuid, 'name' => $user->name];
    }
}
