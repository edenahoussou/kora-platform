<?php

namespace App\Modules\Parcels\Http\Resources;

use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelEvent;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Settings\Settings\GeneralSettings;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Parcel */
class ParcelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $parcel = $this->resource;
        $returnCharge = in_array($parcel->status, ['return_pending', 'returned'], true);
        $charge = $parcel->charges->first(fn ($charge) => $charge->superseded_at === null && $charge->return_leg === $returnCharge);
        $chargeSettled = $charge?->settled_payment_id !== null;
        $user = $request->user();
        $settings = app(OperationsSettings::class);
        $assignment = $parcel->assignments->sortByDesc('id')->first(fn ($assignment) => $assignment->released_at === null || $assignment->transported_at !== null);
        $departure = $assignment?->departure;
        $handoffEvent = $parcel->latestCustodyEvent;
        $receivedEvent = $handoffEvent !== null && in_array($handoffEvent->kind, ['available', 'returned'], true) ? $handoffEvent : null;
        $deliveredEvent = $handoffEvent !== null && in_array($handoffEvent->kind, ['withdrawn', 'return_handed_over'], true) ? $handoffEvent : null;
        $handoffHub = $handoffEvent !== null ? $handoffEvent->hub : $parcel->custodyHub;
        $handoffHubAccess = function (?ParcelHub $hub, string $permission) use ($user): bool {
            return $hub !== null
                && $user !== null
                && $user->can($permission)
                && ($user->agency_id === $hub->agency_id || $user->can('parcels.network'));
        };
        $awaitsHandoff = ($parcel->status === 'available' && $parcel->return_due_at?->isFuture() === true)
            || ($parcel->status === 'returned' && $parcel->return_handed_over_at === null);
        $hasReturnCharge = $parcel->charges->contains(fn ($candidate): bool => $candidate->superseded_at === null && $candidate->return_leg);
        $returnFeeSettled = $parcel->status !== 'returned' || ($charge !== null && $chargeSettled);
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
            'can_receipt' => $chargeSettled,
            'can_ready' => $parcel->status === 'registered' && $chargeSettled && ($user->can('parcels.intake')),
            'can_assign' => in_array($parcel->status, ['ready', 'assigned', 'return_pending'], true)
                && $chargeSettled
                && ($user->can('parcels.assign')),
            'can_manifest' => $departure !== null
                && ($user->can('parcels.network'))
                && ($user->can('parcels.view')),
            'can_receive' => in_array($parcel->status, ['arrived', 'return_arrived'], true)
                && $handoffHubAccess($handoffHub, 'parcels.handoff'),
            'can_release' => $awaitsHandoff && $returnFeeSettled && $handoffHubAccess($handoffHub, 'parcels.handoff'),
            'can_reissue' => $awaitsHandoff && $returnFeeSettled && $handoffHubAccess($handoffHub, 'parcels.override'),
            'can_override_handoff' => $awaitsHandoff && $returnFeeSettled && $handoffHubAccess($handoffHub, 'parcels.override'),
            'can_return_charge' => $parcel->status === 'returned'
                && ! $hasReturnCharge
                && $handoffHubAccess($parcel->corridor?->originHub, 'parcels.intake'),
            'can_pay_return' => $parcel->status === 'returned'
                && $charge !== null
                && ! $chargeSettled
                && $handoffHubAccess($parcel->corridor?->originHub, 'parcels.intake'),
            'can_print_handoff_proof' => $deliveredEvent !== null
                && $handoffHubAccess($deliveredEvent->hub, 'parcels.handoff'),
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
            'handoff' => [
                'received_at' => $receivedEvent?->observed_at?->toIso8601String(),
                'received_by' => $receivedEvent?->actor?->name,
                'delivered_at' => $deliveredEvent?->observed_at?->toIso8601String(),
                'delivered_by' => $deliveredEvent?->actor?->name,
                'hub_uuid' => $handoffHub?->uuid,
                'hub_name' => $handoffHub?->stop?->name,
                'outcome' => $deliveredEvent === null
                    ? null
                    : ($deliveredEvent->kind === 'return_handed_over' ? 'returned' : 'withdrawn'),
                'method' => $deliveredEvent === null
                    ? null
                    : (isset($deliveredEvent->details['identity_type']) ? 'identity' : 'code'),
                'identity_type' => $deliveredEvent?->details['identity_type'] ?? null,
                'attempts' => (int) $parcel->handoff_attempts,
                'max_attempts' => $settings->parcelHandoffMaxAttempts,
                'attempts_remaining' => max(0, $settings->parcelHandoffMaxAttempts - (int) $parcel->handoff_attempts),
            ],
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
        return ['corridor.originHub.stop', 'corridor.destinationHub.stop', 'custodyHub.stop', 'depositHub.stop', 'inspectionHub.stop', 'sellingAgency', 'charges.tariff', 'assignments.departure.latestTrackingSession', 'incidents', 'latestCustodyEvent.actor', 'latestCustodyEvent.hub.stop'];
    }
}
