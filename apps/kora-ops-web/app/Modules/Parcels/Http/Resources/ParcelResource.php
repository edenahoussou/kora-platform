<?php

namespace App\Modules\Parcels\Http\Resources;

use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Parcel */
class ParcelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $parcel = $this->resource;
        $charge = $parcel->charges->first(fn ($charge) => $charge->superseded_at === null && $charge->return_leg === ($parcel->status === 'returned'));
        $assignment = $parcel->assignments->sortByDesc('id')->first(fn ($assignment) => $assignment->released_at === null || $assignment->transported_at !== null);
        $departure = $assignment?->departure;

        return [
            'uuid' => $parcel->uuid,
            'reference' => $parcel->reference,
            'status' => $parcel->status,
            'origin' => $parcel->corridor?->originHub?->stop?->city,
            'destination' => $parcel->corridor?->destinationHub?->stop?->city,
            'sender_name' => $parcel->sender_name,
            'recipient_name' => $parcel->recipient_name,
            'contents' => $parcel->contents,
            'weight_grams' => $parcel->weight_grams,
            'declared_grams' => $parcel->declared_grams,
            'origin_hub_uuid' => $parcel->corridor?->originHub?->uuid,
            'destination_hub_uuid' => $parcel->corridor?->destinationHub?->uuid,
            'custody_hub_uuid' => $parcel->custodyHub?->uuid,
            'withdraw_by' => $parcel->return_due_at?->toIso8601String(),
            'return_handed_over_at' => $parcel->return_handed_over_at?->toIso8601String(),
            'expires_at' => $parcel->expires_at->toIso8601String(),
            'updated_at' => $parcel->updated_at?->toIso8601String(),
            'departure_uuid' => $departure?->uuid,
            'eta' => $assignment?->unloaded_at?->toIso8601String()
                ?? $departure?->estimated_arrival_at?->copy()->addMinutes($departure->delay_minutes)->toIso8601String(),
            'tracking_updated_at' => $departure?->latestTrackingSession?->last_position_at?->toIso8601String(),
            'incident_open' => $parcel->incidents->contains(fn ($incident) => $incident->resolved_at === null),
            'quote' => $charge === null ? null : [
                'uuid' => $charge->uuid, 'net' => $charge->net, 'tax' => $charge->tax, 'total' => $charge->total, 'currency' => 'XOF',
            ],
            'timeline' => $this->whenLoaded('events', fn () => $parcel->events->map(fn (ParcelEvent $event): array => [
                'kind' => $event->kind, 'observed_at' => $event->observed_at->toIso8601String(),
            ])),
        ];
    }

    /** @return list<string> */
    public static function relations(): array
    {
        return ['corridor.originHub.stop', 'corridor.destinationHub.stop', 'custodyHub', 'charges', 'assignments.departure.latestTrackingSession', 'incidents'];
    }
}
