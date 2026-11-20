<?php

namespace App\Modules\Parcels\Http\Resources;

use App\Modules\Parcels\Data\ParcelEventKind;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Parcel */
class ParcelPublicTrackingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $parcel = $this->resource;
        $events = $parcel->events
            ->filter(fn (ParcelEvent $event): bool => ParcelEventKind::isPublic($event->kind))
            ->sortBy('observed_at')
            ->values();
        $assignment = $parcel->assignments
            ->sortByDesc('id')
            ->first(fn ($assignment): bool => $assignment->released_at === null || $assignment->transported_at !== null);
        $departure = $assignment?->departure;
        $eta = $assignment?->unloaded_at?->toIso8601String()
            ?? $departure?->estimated_arrival_at?->copy()->addMinutes((int) ($departure->delay_minutes ?? 0))->toIso8601String();

        return [
            'reference' => $parcel->reference,
            'status' => $parcel->incidents->contains(
                fn ($incident): bool => $incident->blocking && $incident->resolved_at === null,
            ) ? 'incident' : $parcel->status,
            'origin' => $parcel->corridor?->originHub?->stop?->city,
            'destination' => $parcel->corridor?->destinationHub?->stop?->city,
            'weight_grams' => $parcel->weight_grams ?? $parcel->declared_grams,
            'withdraw_by' => $parcel->return_due_at?->toIso8601String(),
            'eta' => $eta,
            'last_event_at' => $events->last()?->observed_at?->toIso8601String(),
            'timeline' => $events->map(fn (ParcelEvent $event): array => [
                'kind' => $event->kind,
                'observed_at' => $event->observed_at->toIso8601String(),
                'city' => $event->hub?->stop?->city,
            ]),
        ];
    }

    /** @return list<string> */
    public static function relations(): array
    {
        return [
            'corridor.originHub.stop',
            'corridor.destinationHub.stop',
            'events.hub.stop',
            'incidents',
            'assignments.departure',
        ];
    }
}
