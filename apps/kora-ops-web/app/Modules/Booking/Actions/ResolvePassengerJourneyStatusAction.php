<?php

namespace App\Modules\Booking\Actions;

use App\Modules\Booking\Data\PassengerJourneyStatus;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;

class ResolvePassengerJourneyStatusAction
{
    /**
     * @param  Payment|null  $latestPayment  Most recent payment on the reservation (any single
     *                                       payment covers every passenger in the same purchase —
     *                                       there is no per-passenger payment breakdown).
     */
    public function execute(ReservationSegment $segment, ?Payment $latestPayment, DepartureStatus $departureStatus): PassengerJourneyStatus
    {
        if ($segment->status === ReservationSegmentStatus::Boarded) {
            return PassengerJourneyStatus::Boarded;
        }

        if (in_array($segment->status, [ReservationSegmentStatus::Cancelled, ReservationSegmentStatus::Released], true)) {
            return PassengerJourneyStatus::Cancelled;
        }

        // "Absent" only applies once the bus has actually left/arrived: a
        // ticketed passenger who simply hasn't boarded YET (departure still
        // scheduled/boarding) is not absent, just not-yet-boarded.
        if (in_array($departureStatus, [DepartureStatus::Departed, DepartureStatus::Arrived], true)
            && $segment->status === ReservationSegmentStatus::Ticketed) {
            return PassengerJourneyStatus::Absent;
        }

        if ($segment->status === ReservationSegmentStatus::Expired) {
            return PassengerJourneyStatus::HoldExpired;
        }

        if ($segment->status === ReservationSegmentStatus::Held) {
            $expiresAt = $segment->reservation?->expires_at;

            if ($expiresAt !== null && $expiresAt->isPast()) {
                return PassengerJourneyStatus::HoldExpired;
            }

            return PassengerJourneyStatus::HoldPending;
        }

        if ($segment->status === ReservationSegmentStatus::Ticketed) {
            return PassengerJourneyStatus::TicketIssued;
        }

        // Confirmed or Paid segment status: distinguished by the reservation's
        // most recent payment. No payment row yet, or still pending, reads as
        // "payment pending" — only an explicitly Confirmed payment counts as
        // paid, since Failed/Cancelled payments must not be read as success.
        if ($latestPayment?->status === PaymentStatus::Confirmed) {
            return PassengerJourneyStatus::PaymentConfirmed;
        }

        return PassengerJourneyStatus::PaymentPending;
    }
}
