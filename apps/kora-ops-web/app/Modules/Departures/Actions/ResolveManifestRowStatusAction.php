<?php

namespace App\Modules\Departures\Actions;

use App\Modules\Booking\Actions\ResolvePassengerJourneyStatusAction;
use App\Modules\Booking\Data\PassengerJourneyStatus;
use App\Modules\Booking\Data\ReservationSegmentStatus;
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
     * vocabulaire exposé au convoyeur. Retourne null pour les
     * réservations pas encore payées/confirmées (hold, paiement en
     * attente, hold expiré) : ces passagers n'ont pas de billet à
     * présenter et ne doivent pas apparaître sur le manifeste.
     *
     * @param  bool  $wasReleasedAsNoShow  Segment "released" spécifiquement
     *                                     via un no-show (voir ResolveManifestSegmentsAction) — distingue du
     *                                     cas générique "released" (descente anticipée), que
     *                                     PassengerJourneyStatus ne différencie pas non plus.
     */
    public function execute(ReservationSegment $segment, ?Payment $latestPayment, DepartureStatus $departureStatus, bool $wasReleasedAsNoShow = false): ?ManifestSegmentStatus
    {
        $journeyStatus = $this->resolveJourneyStatus->execute($segment, $latestPayment, $departureStatus);

        // PassengerJourneyStatus::Cancelled recouvre deux réalités bien
        // distinctes côté ReservationSegmentStatus : une vraie annulation
        // (Cancelled — remboursement, etc.) et un siège libéré (Released
        // — descente anticipée ou no-show). On les sépare ici sur le
        // statut du segment lui-même plutôt que sur le journey status,
        // qui les confond volontairement pour le reste de l'app.
        if ($journeyStatus === PassengerJourneyStatus::Cancelled && $segment->status === ReservationSegmentStatus::Released) {
            return $wasReleasedAsNoShow ? ManifestSegmentStatus::NoShow : ManifestSegmentStatus::EarlyDropOff;
        }

        return match ($journeyStatus) {
            PassengerJourneyStatus::PaymentConfirmed, PassengerJourneyStatus::TicketIssued => ManifestSegmentStatus::Paid,
            PassengerJourneyStatus::Boarded => ManifestSegmentStatus::Boarded,
            PassengerJourneyStatus::Absent => ManifestSegmentStatus::NoShow,
            PassengerJourneyStatus::Cancelled => ManifestSegmentStatus::Cancelled,
            default => null,
        };
    }
}
