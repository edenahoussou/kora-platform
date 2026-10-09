<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Notifications\Jobs\SendPassengerDepartureReminderJob;
use App\Modules\Notifications\Notifications\PassengerInboxNotification;
use App\Modules\Payments\Data\PaymentRefundStatus;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Settings\Settings\OperationsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class PassengerNotificationPublisher
{
    public function reservationConfirmed(Reservation $reservation): void
    {
        $journey = $this->reservationJourney($reservation);
        $message = $journey === null
            ? 'Votre réservation est confirmée. Votre billet est disponible dans l’application.'
            : "Votre trajet {$journey} est confirmé. Votre billet est disponible dans l’application.";
        $this->publishReservation($reservation, 'reservation_confirmed', 'Réservation confirmée', $message, 'tickets');
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
        $this->publishReservation($reservation, 'ticket_issuance_failed', 'Votre billet est en cours de traitement', 'Le paiement est confirmé, mais l’émission du billet n’a pas abouti. Notre équipe traite la situation.', 'tickets');
    }

    public function departureChanged(Departure $departure, string $change): void
    {
        $departure->loadMissing('line.stops.physicalStop');
        $reservations = Reservation::query()->whereHas('segments', fn ($query) => $query->where('departure_id', $departure->getKey()))
            ->where('status', ReservationStatus::Ticketed->value)
            ->with(['payer', 'passengers.user', 'segments.originStop.physicalStop', 'segments.destinationStop.physicalStop'])
            ->get();

        foreach ($reservations as $reservation) {
            $title = $change === 'cancelled' ? 'Départ annulé' : 'Départ modifié';
            $message = $change === 'cancelled'
                ? 'Votre départ a été annulé. Consultez votre réservation pour connaître les suites.'
                : $this->changedDepartureMessage($reservation, $departure);
            $this->publishReservation($reservation, 'departure_'.$change, $title, $message, 'tickets', $departure->uuid.':'.$change.':'.$departure->updated_at?->getTimestamp());
        }
    }

    public function departureDelayed(Departure $departure, string $etaKey): void
    {
        $departure->loadMissing('line.stops.physicalStop');
        $estimatedTime = CarbonImmutable::parse($etaKey)
            ->setTimezone($departure->departure_timezone ?? config('app.timezone'))
            ->format('H\\hi');
        foreach (Reservation::query()->whereHas('segments', fn ($query) => $query->where('departure_id', $departure->getKey()))
            ->where('status', ReservationStatus::Ticketed->value)
            ->with(['payer', 'passengers.user', 'segments.originStop.physicalStop', 'segments.destinationStop.physicalStop'])
            ->get() as $reservation) {
            $route = $this->reservationSegmentRoute($reservation, $departure);
            $routeText = $route === null ? 'votre départ' : "votre départ {$route}";
            $message = "Un retard est annoncé pour {$routeText}. Le nouvel horaire estimé est {$estimatedTime}. Consultez votre billet pour les informations à jour.";
            $this->publishReservation($reservation, 'departure_delayed', 'Départ retardé', $message, 'tickets', $departure->uuid.':'.$etaKey);
        }
    }

    public function departureReminder(Reservation $reservation, Departure $departure, int $offsetHours, string $dedupeSuffix): void
    {
        $departure->loadMissing('line.stops.physicalStop');
        $reservation->loadMissing(['segments.originStop.physicalStop', 'segments.destinationStop.physicalStop']);
        $route = $this->reservationSegmentRoute($reservation, $departure);
        $destination = $this->departureDestination($departure);
        $routeText = $route === null ? 'Votre trajet' : "Votre trajet {$route}";
        $time = $departure->scheduledDepartureAt()
            ->addMinutes((int) $departure->delay_minutes)
            ->setTimezone($departure->departure_timezone ?? config('app.timezone'))
            ->format('H\\hi');
        $when = $offsetHours === 24
            ? "part demain à {$time}"
            : "part dans environ {$offsetHours} heures";
        $title = $offsetHours === 24 ? 'Votre départ est prévu demain' : 'Votre départ approche';
        $message = "{$routeText} {$when}. Retrouvez votre billet dans l’application.";

        if ($offsetHours === 2 && $destination !== null) {
            $message = "Votre bus pour {$destination} part dans environ 2 heures. Pensez à vous présenter à la gare en avance.";
        }

        $this->publishReservation($reservation, 'departure_reminder', $title, $message, 'tickets', $dedupeSuffix);
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

        [$title, $message] = match ($refund->status) {
            PaymentRefundStatus::Pending => [
                'Demande de remboursement reçue',
                'Votre demande a été prise en compte. Nous vous informerons de son évolution.',
            ],
            PaymentRefundStatus::Completed => [
                'Remboursement effectué',
                'Le remboursement de votre réservation a été effectué. Consultez votre compte pour les détails.',
            ],
            PaymentRefundStatus::Failed => [
                'Remboursement non effectué',
                'Le remboursement n’a pas abouti. Notre équipe vous indiquera la suite à donner.',
            ],
        };

        $this->publishReservation($reservation, 'refund_'.$refund->status->value, $title, $message, 'tickets', $refund->reference);
    }

    private function changedDepartureMessage(Reservation $reservation, Departure $departure): string
    {
        $route = $this->reservationSegmentRoute($reservation, $departure);
        $destination = $this->departureDestination($departure);
        $destinationText = $destination === null ? '' : " vers {$destination}";
        $routeText = $route === null ? "Votre départ{$destinationText}" : "Votre départ {$route}";
        $scheduledAt = $departure->scheduledDepartureAt()
            ->setTimezone($departure->departure_timezone ?? config('app.timezone'))
            ->format('d/m \\à H\\hi');

        return "{$routeText} est désormais prévu le {$scheduledAt}. Consultez votre billet pour voir les détails.";
    }

    private function reservationJourney(Reservation $reservation): ?string
    {
        $reservation->loadMissing(['segments.originStop.physicalStop', 'segments.destinationStop.physicalStop']);
        $segments = $reservation->segments->sortBy('leg_order')->values();
        $origin = $segments->first()?->originStop;
        $destination = $segments->last()?->destinationStop;

        return $this->joinRouteLabels($this->stopLabel($origin), $this->stopLabel($destination));
    }

    private function reservationSegmentRoute(Reservation $reservation, Departure $departure): ?string
    {
        $segment = $reservation->segments->firstWhere('departure_id', $departure->getKey());

        if ($segment === null) {
            return null;
        }

        return $this->joinRouteLabels($this->stopLabel($segment->originStop), $this->stopLabel($segment->destinationStop));
    }

    private function departureDestination(Departure $departure): ?string
    {
        $destination = $departure->tripStops()->last();

        return $destination?->physicalStop?->city ?: $destination?->city ?: $destination?->name;
    }

    private function stopLabel(?Stop $stop): ?string
    {
        if ($stop === null) {
            return null;
        }

        return $stop->physicalStop?->city ?: $stop->city ?: $stop->name;
    }

    private function joinRouteLabels(?string $origin, ?string $destination): ?string
    {
        return $origin !== null && $destination !== null ? "{$origin} → {$destination}" : null;
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
