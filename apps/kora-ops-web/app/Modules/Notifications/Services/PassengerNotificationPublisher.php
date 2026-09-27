<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Departures\Models\Departure;
use App\Modules\Notifications\Jobs\SendPassengerDepartureReminderJob;
use App\Modules\Notifications\Notifications\PassengerInboxNotification;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class PassengerNotificationPublisher
{
    public function reservationConfirmed(Reservation $reservation): void
    {
        $this->publishReservation($reservation, 'reservation_confirmed', 'Réservation confirmée', 'Votre réservation est confirmée. Votre billet est disponible dans l’application.', 'tickets');
        $reservation->loadMissing('segments.departure');
        foreach ($reservation->segments as $segment) {
            foreach (app(OperationsSettings::class)->passengerDepartureReminderHours as $hours) {
                $departure = $segment->departure;
                if ($departure === null) {
                    continue;
                }
                $effectiveDepartureAt = $departure->scheduledDepartureAt()->addMinutes((int) $departure->delay_minutes);
                $remindAt = $effectiveDepartureAt->subHours($hours);
                if ($remindAt->isFuture()) {
                    SendPassengerDepartureReminderJob::dispatch(
                        (int) $reservation->getKey(), (int) $departure->getKey(), (int) $hours,
                        $effectiveDepartureAt->toIso8601String(),
                    )->delay($remindAt);
                }
            }
        }
    }

    public function ticketIssuanceFailed(Reservation $reservation): void
    {
        $this->publishReservation($reservation, 'ticket_issuance_failed', 'Billet en cours de traitement', 'Le paiement est confirmé, mais l’émission du billet a rencontré un problème. Notre équipe traite la demande.', 'tickets');
    }

    public function departureChanged(Departure $departure, string $change): void
    {
        $reservations = Reservation::query()->whereHas('segments', fn ($query) => $query->where('departure_id', $departure->getKey()))
            ->where('status', ReservationStatus::Ticketed->value)
            ->with(['payer', 'passengers.user'])
            ->get();

        foreach ($reservations as $reservation) {
            $title = $change === 'cancelled' ? 'Départ annulé' : 'Départ modifié';
            $message = $change === 'cancelled'
                ? 'Votre départ a été annulé. Consultez votre réservation pour connaître les suites.'
                : 'Les informations de votre départ ont changé. Consultez votre billet pour voir les détails.';
            $this->publishReservation($reservation, 'departure_'.$change, $title, $message, 'tickets', $departure->uuid.':'.$change.':'.$departure->updated_at?->getTimestamp());
        }
    }

    public function departureDelayed(Departure $departure, string $etaKey): void
    {
        $departure->loadMissing('line');
        $message = sprintf('Votre départ %s est retardé. Consultez votre billet pour les informations à jour.', $departure->line->code);
        foreach (Reservation::query()->whereHas('segments', fn ($query) => $query->where('departure_id', $departure->getKey()))->where('status', ReservationStatus::Ticketed->value)->with(['payer', 'passengers.user'])->get() as $reservation) {
            $this->publishReservation($reservation, 'departure_delayed', 'Départ retardé', $message, 'tickets', $departure->uuid.':'.$etaKey);
        }
    }

    public function departureReminder(Reservation $reservation, string $offset): void
    {
        $this->publishReservation($reservation, 'departure_reminder', 'Votre départ approche', 'Votre trajet en bus approche. Retrouvez votre billet et les informations de départ dans l’application.', 'tickets', $offset);
    }

    public function scheduleDepartureReminders(Departure $departure): void
    {
        foreach (Reservation::query()->whereHas('segments', fn ($query) => $query->where('departure_id', $departure->getKey()))
            ->where('status', ReservationStatus::Ticketed->value)->get() as $reservation) {
            foreach (app(OperationsSettings::class)->passengerDepartureReminderHours as $hours) {
                $effectiveDepartureAt = $departure->scheduledDepartureAt()->addMinutes((int) $departure->delay_minutes);
                $remindAt = $effectiveDepartureAt->subHours($hours);
                if ($remindAt->isFuture()) {
                    SendPassengerDepartureReminderJob::dispatch(
                        (int) $reservation->getKey(), (int) $departure->getKey(), (int) $hours,
                        $effectiveDepartureAt->toIso8601String(),
                    )->delay($remindAt);
                }
            }
        }
    }

    public function refundRecorded(PaymentRefund $refund): void
    {
        $reservation = $refund->reservation()->with(['payer', 'passengers.user'])->first();
        if ($reservation === null) {
            return;
        }

        $message = $refund->status->value === 'completed'
            ? 'Le remboursement de votre réservation a été effectué.'
            : 'Le remboursement de votre réservation a été initié. Vous serez informé de son évolution.';
        $this->publishReservation($reservation, 'refund_'.$refund->status->value, 'Mise à jour de remboursement', $message, 'tickets', $refund->reference);
    }

    private function publishReservation(
        Reservation $reservation,
        string $type,
        string $title,
        string $message,
        string $resource,
        ?string $suffix = null,
    ): void {
        $recipients = $this->recipients($reservation);
        $dedupeSuffix = $suffix ?? (string) $reservation->ticket_issued_at?->getTimestamp();
        foreach ($recipients as $user) {
            Notification::sendNow($user, new PassengerInboxNotification(
                dedupeKey: 'bus:'.$type.':'.$reservation->uuid.':'.$dedupeSuffix,
                type: $type,
                title: $title,
                message: $message,
                resourceUuid: $reservation->uuid,
                data: ['resource' => $resource, 'reservation_uuid' => $reservation->uuid],
            ));
        }
    }

    /** @return Collection<int, User> */
    private function recipients(Reservation $reservation): Collection
    {
        $reservation->loadMissing(['payer', 'passengers.user']);

        if ($reservation->payer_type === PayerType::Personal && $reservation->payer_user_id !== null && $reservation->payer !== null) {
            return new Collection([$reservation->payer]);
        }

        return $reservation->passengers
            ->where('status', 'active')
            ->map(fn ($passenger) => $passenger->user)
            ->filter(fn ($user): bool => $user instanceof User)
            ->unique('id')
            ->values();
    }
}
