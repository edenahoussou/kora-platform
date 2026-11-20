<?php

namespace App\Modules\Parcels\Http\Resources;

use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelEvent;
use App\Modules\Settings\Settings\GeneralSettings;
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
        $trackingUrl = is_string($parcel->reference) && $parcel->reference !== ''
            ? route('api.v1.parcels.tracking', ['reference' => $parcel->reference])
            : null;
        $inspectionState = match (true) {
            $parcel->status === 'refused' => 'refused',
            $parcel->inspected_at !== null => 'accepted',
            default => 'pending_inspection',
        };

        return [
            'uuid' => $parcel->uuid,
            'reference' => $parcel->reference,
            'tracking_url' => $trackingUrl,
            'qr_payload' => $trackingUrl,
            'status' => $parcel->status,
            'origin' => $parcel->corridor?->originHub?->stop?->city,
            'destination' => $parcel->corridor?->destinationHub?->stop?->city,
            'sender_name' => $parcel->sender_name,
            'recipient_name' => $parcel->recipient_name,
            'contents' => $parcel->contents,
            'weight_grams' => $parcel->weight_grams,
            'declared_grams' => $parcel->declared_grams,
            'inspection_state' => $inspectionState,
            'inspection' => [
                'state' => $inspectionState,
                'category' => $parcel->inspection_category?->value,
                'refusal_reason' => $parcel->inspection_refusal_reason,
                'inspected_at' => $parcel->inspected_at?->toIso8601String(),
                'hub_uuid' => $parcel->inspectionHub?->uuid,
                'hub_name' => $parcel->inspectionHub?->stop?->name,
            ],
            'can_pay' => $parcel->status === 'weighed' && $charge !== null && ! $parcel->incidents->contains(fn ($incident) => $incident->blocking && $incident->resolved_at === null) && $parcel->expires_at->isFuture(),
            'can_receipt' => $charge !== null && $charge->settled_payment_id !== null,
            'can_assign' => $parcel->status === 'ready',
            'origin_hub_uuid' => $parcel->corridor?->originHub?->uuid,
            'destination_hub_uuid' => $parcel->corridor?->destinationHub?->uuid,
            'custody_hub_uuid' => $parcel->custodyHub?->uuid,
            'deposit_hub_uuid' => $parcel->depositHub?->uuid,
            'deposit_hub' => $parcel->depositHub === null ? null : [
                'uuid' => $parcel->depositHub->uuid,
                'kind' => $parcel->depositHub->kind,
                'city' => $parcel->depositHub->stop?->city,
            ],
            'selling_agency_uuid' => $parcel->sellingAgency?->uuid,
            'selling_agency' => $parcel->sellingAgency === null ? null : [
                'uuid' => $parcel->sellingAgency->uuid,
                'name' => $parcel->sellingAgency->name,
            ],
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
                'uuid' => $charge->uuid,
                'net' => $charge->net,
                'tax' => $charge->tax,
                'total' => $charge->total,
                'currency' => app(GeneralSettings::class)->currency,
                'tariff_uuid' => $charge->tariff?->uuid,
                'tariff_version' => $charge->tariff?->version,
                'deposit_kind' => $charge->tariff?->deposit_kind,
                'base_amount' => $charge->tariff?->base_amount,
                'step_amount' => $charge->tariff?->step_amount,
                'step_grams' => $charge->tariff?->step_grams,
                'maximum_grams' => $charge->tariff?->maximum_grams,
            ],
            'timeline' => $this->whenLoaded('events', fn () => $parcel->events->map(fn (ParcelEvent $event): array => [
                'kind' => $event->kind, 'observed_at' => $event->observed_at->toIso8601String(),
            ])),
        ];
    }

    /** @return list<string> */
    public static function relations(): array
    {
        return ['corridor.originHub.stop', 'corridor.destinationHub.stop', 'custodyHub', 'depositHub.stop', 'inspectionHub.stop', 'sellingAgency', 'charges.tariff', 'assignments.departure.latestTrackingSession', 'incidents'];
    }
}
