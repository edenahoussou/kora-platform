<?php

namespace App\Modules\Parcels\Data;

/**
 * Catalogue canonique des événements Colis.
 *
 * Un kind est soit publiable dans la timeline de suivi, soit réservé aux
 * opérations internes. La whitelist publique ne doit jamais contenir un
 * événement de paiement, d'incident détaillé, de partage ou d'audit OPS.
 */
enum ParcelEventKind: string
{
    case Preregistered = 'preregistered';
    case Weighed = 'weighed';
    case Refused = 'refused';
    case Registered = 'registered';
    case Ready = 'ready';
    case Assigned = 'assigned';
    case AssignmentReleased = 'assignment_released';
    case Loaded = 'loaded';
    case InTransit = 'in_transit';
    case ReturnTransit = 'return_transit';
    case Unloaded = 'unloaded';
    case Arrived = 'arrived';
    case Available = 'available';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case ReturnPending = 'return_pending';
    case ReturnArrived = 'return_arrived';
    case Returned = 'returned';
    case ReturnHandedOver = 'return_handed_over';
    case Cancelled = 'cancelled';
    case ReturnChargeCreated = 'return_charge_created';
    case ReturnFeePaid = 'return_fee_paid';
    case IncidentReported = 'incident_reported';
    case IncidentOpened = 'incident_opened';
    case IncidentResolved = 'incident_resolved';
    case HandoffCodeReissued = 'handoff_code_reissued';
    case HandoffCodeRejected = 'handoff_code_rejected';
    case TrackingLinkIssued = 'tracking_link_issued';
    case TrackingLinkRevoked = 'tracking_link_revoked';
    case PrintRequested = 'print_requested';
    case HandoffProofRequested = 'handoff_proof_requested';
    case PrintResult = 'print_result';
    case DepartureCancelled = 'departure_cancelled';
    case DepartureChanged = 'departure_changed';
    case LoadedDepartureCancelled = 'loaded_departure_cancelled';
    case UnloadedAtOrigin = 'unloaded_at_origin';
    case DuplicateProviderReceipt = 'duplicate_provider_receipt';
    case PaymentReconciliationRequired = 'payment_reconciliation_required';
    case RefundAuthorized = 'refund_authorized';
    case RefundCompleted = 'refund_completed';

    /** @return list<string> */
    public static function publicTimeline(): array
    {
        return [
            self::Preregistered->value,
            self::Weighed->value,
            self::Refused->value,
            self::Registered->value,
            self::Ready->value,
            self::Assigned->value,
            self::Loaded->value,
            self::InTransit->value,
            self::ReturnTransit->value,
            self::Unloaded->value,
            self::Arrived->value,
            self::Available->value,
            self::Withdrawn->value,
            self::Expired->value,
            self::ReturnPending->value,
            self::ReturnArrived->value,
            self::Returned->value,
            self::ReturnHandedOver->value,
            self::Cancelled->value,
        ];
    }

    /** @return list<string> */
    public static function operationsOnly(): array
    {
        return array_values(array_diff(
            array_map(fn (self $kind): string => $kind->value, self::cases()),
            self::publicTimeline(),
        ));
    }

    public static function isPublic(string $kind): bool
    {
        return in_array($kind, self::publicTimeline(), true);
    }

    public static function isKnown(string $kind): bool
    {
        return self::tryFrom($kind) !== null;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $kind): string => $kind->value, self::cases());
    }
}
