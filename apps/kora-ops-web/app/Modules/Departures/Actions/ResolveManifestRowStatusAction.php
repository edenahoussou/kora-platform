<?php

namespace App\Modules\Departures\Actions;

use App\Modules\Booking\Actions\ResolvePassengerJourneyStatusAction;
use App\Modules\Booking\Data\PassengerJourneyStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Data\ManifestSegmentStatus;
use App\Modules\Payments\Models\Payment;

class ResolveManifestRowStatusAction
{
    public function __construct(
        private ResolvePassengerJourneyStatusAction $resolveJourneyStatus,
    ) {}

    /**
     * Ramène le statut interne (PassengerJourneyStatus, 8 valeurs) au
     * vocabulaire à 4 valeurs exposé au convoyeur. Retourne null pour les
     * réservations pas encore payées/confirmées (hold, paiement en
     * attente, hold expiré) : ces passagers n'ont pas de billet à
     * présenter et ne doivent pas apparaître sur le manifeste.
     */
    public function execute(ReservationSegment $segment, ?Payment $latestPayment, DepartureStatus $departureStatus): ?ManifestSegmentStatus
    {
        $journeyStatus = $this->resolveJourneyStatus->execute($segment, $latestPayment, $departureStatus);

        return match ($journeyStatus) {
            PassengerJourneyStatus::PaymentConfirmed, PassengerJourneyStatus::TicketIssued => ManifestSegmentStatus::Paid,
            PassengerJourneyStatus::Boarded => ManifestSegmentStatus::Boarded,
            PassengerJourneyStatus::Absent => ManifestSegmentStatus::NoShow,
            PassengerJourneyStatus::Cancelled => ManifestSegmentStatus::Cancelled,
            default => null,
        };
    }
}
