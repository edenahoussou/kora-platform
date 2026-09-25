<?php

namespace App\Modules\Departures\Services;

use App\Modules\Booking\Actions\ResolvePassengerJourneyStatusAction;
use App\Modules\Booking\Data\PassengerJourneyStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

/**
 * Sérialisation des lignes passagers d'un manifeste / d'une fiche départ.
 * Partagée entre ManifestController et DepartureDetailService pour que
 * la fiche départ (onglet Passagers) et le manifeste affichent la même
 * vérité — siège, segment O/D, statut de trajet, billet.
 */
class ManifestPassengerSerializer
{
    /** Libellés des types de pièce (valeurs de PassengerDocumentType). */
    private const array DOCUMENT_LABELS = [
        'passport' => 'Passeport',
        'national_id' => 'CNI',
        'biometric_card' => 'Carte biométrique',
        'consular_card' => 'Carte consulaire',
        'cip' => 'CIP',
    ];

    public function __construct(
        private readonly ResolvePassengerJourneyStatusAction $resolveJourneyStatus,
    ) {}

    /**
     * @param  Collection<int, ReservationSegment>  $segments
     * @return array<int, Ticket>
     */
    public function ticketsByPassengerId(Collection $segments): array
    {
        $passengerIds = $segments->pluck('reservation_passenger_id')->filter()->unique()->values();

        if ($passengerIds->isEmpty()) {
            return [];
        }

        return Ticket::query()
            ->whereIn('reservation_passenger_id', $passengerIds)
            ->get()
            ->keyBy('reservation_passenger_id')
            ->all();
    }

    /**
     * @param  array<int, Ticket>  $ticketsByPassengerId
     * @return array<string, mixed>
     */
    public function row(ReservationSegment $segment, array $ticketsByPassengerId, bool $canViewTickets, DepartureStatus $departureStatus, bool $includeDeparture = false): array
    {
        $passenger = $segment->reservationPassenger;
        // reservation_id, seat_id, origin_stop_id, destination_stop_id and
        // departure_id are all NOT NULL on reservation_segments, so these
        // relations are never null — accessed directly, no nullsafe/fallback.
        $reservation = $segment->reservation;
        $ticket = $passenger !== null ? ($ticketsByPassengerId[$passenger->id] ?? null) : null;
        $latestPayment = $reservation->payments->first();

        return [
            'segmentUuid' => $segment->uuid,
            'segmentStatus' => (string) $segment->getRawOriginal('status'),
            'passenger' => $passenger === null ? null : [
                'uuid' => $passenger->uuid,
                'firstName' => $passenger->first_name,
                'lastName' => $passenger->last_name,
                'phone' => $passenger->phone,
                'nationality' => $passenger->nationality,
                'documentTypeLabel' => $passenger->document_type === null
                    ? null
                    : self::DOCUMENT_LABELS[$passenger->document_type->value],
                'documentNumber' => $passenger->document_number,
                'birthDate' => $passenger->birth_date?->format('d/m/Y'),
            ],
            'seatCode' => $segment->seat->code,
            'originStop' => $segment->originStop->name,
            'originStopOrder' => (int) $segment->origin_stop_order,
            'destinationStop' => $segment->destinationStop->name,
            'destinationStopOrder' => (int) $segment->destination_stop_order,
            'reservation' => [
                'uuid' => $reservation->uuid,
                'reference' => $reservation->reference,
                'isGroupPurchase' => $reservation->relationLoaded('passengers')
                    ? $reservation->passengers->count() > 1
                    : null,
            ],
            'journeyStatus' => $this->journeyStatusPayload($segment, $latestPayment, $departureStatus),
            'ticket' => ! $canViewTickets ? null : ($ticket === null ? null : [
                'uuid' => $ticket->uuid,
                'shortCode' => $ticket->short_code,
                'status' => $ticket->status->value,
                'statusLabel' => $ticket->status->translatedLabel(),
                'issuedAt' => $ticket->issued_at?->toIso8601String(),
            ]),
            'departure' => ! $includeDeparture ? null : [
                'uuid' => $segment->departure->uuid,
                'lineCode' => $segment->departure->line->code,
                'departureDate' => $segment->departure->departure_date?->toDateString(),
                'departureTime' => substr((string) $segment->departure->departure_time, 0, 5),
            ],
        ];
    }

    /**
     * @param  Collection<int, ReservationSegment>  $segments
     * @return array<string, int>
     */
    public function summary(Collection $segments, DepartureStatus $departureStatus): array
    {
        $counts = [
            'total' => $segments->count(),
            'boarded' => 0,
            'absent' => 0,
            'ticketIssued' => 0,
            'paymentPending' => 0,
            'holdExpired' => 0,
            'cancelled' => 0,
            'incompleteIdentities' => 0,
        ];

        foreach ($segments as $segment) {
            $latestPayment = $segment->reservation->payments->first();
            $status = $this->resolveJourneyStatus->execute($segment, $latestPayment, $departureStatus);

            match ($status) {
                PassengerJourneyStatus::Boarded => $counts['boarded']++,
                PassengerJourneyStatus::Absent => $counts['absent']++,
                PassengerJourneyStatus::TicketIssued => $counts['ticketIssued']++,
                PassengerJourneyStatus::HoldPending, PassengerJourneyStatus::PaymentPending => $counts['paymentPending']++,
                PassengerJourneyStatus::HoldExpired => $counts['holdExpired']++,
                PassengerJourneyStatus::Cancelled => $counts['cancelled']++,
                default => null,
            };

            $passenger = $segment->reservationPassenger;
            if ($passenger === null
                || trim((string) $passenger->first_name.' '.$passenger->last_name) === ''
                || $passenger->nationality === null
                || $passenger->nationality === ''
                || $passenger->document_number === null
                || $passenger->document_number === ''
            ) {
                $counts['incompleteIdentities']++;
            }
        }

        return $counts;
    }

    /** @return array{value: string, label: string} */
    private function journeyStatusPayload(ReservationSegment $segment, ?Payment $latestPayment, DepartureStatus $departureStatus): array
    {
        $status = $this->resolveJourneyStatus->execute($segment, $latestPayment, $departureStatus);

        return ['value' => $status->value, 'label' => $status->translatedLabel()];
    }
}
