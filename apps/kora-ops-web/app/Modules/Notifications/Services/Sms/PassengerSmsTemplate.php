<?php

namespace App\Modules\Notifications\Services\Sms;

use App\Modules\Booking\Models\Reservation;

final class PassengerSmsTemplate
{
    /** @param array{route?: string|null, scheduled_at?: string|null, eta?: string|null, tickets?: int|null} $context */
    public function build(string $type, Reservation $reservation, array $context = []): ?string
    {
        $route = filled($context['route'] ?? null) ? (string) $context['route'] : 'votre trajet';
        $scheduledAt = filled($context['scheduled_at'] ?? null) ? ' du '.$context['scheduled_at'] : '';
        $eta = filled($context['eta'] ?? null) ? (string) $context['eta'] : null;

        $message = match ($type) {
            'reservation_confirmed' => "KORA BUS : trajet {$route} confirmé. "
                .$this->ticketsLabel($context['tickets'] ?? null)
                .'Votre billet est dans l’application.',
            'departure_cancelled' => "KORA BUS : votre départ {$route}{$scheduledAt} est annulé. Consultez votre réservation.",
            'departure_delayed' => $eta === null
                ? "KORA BUS : votre départ {$route} est retardé. Consultez votre billet pour l’horaire à jour."
                : "KORA BUS : votre départ {$route} est retardé. Nouvel horaire estimé : {$eta}. Consultez votre billet.",
            default => null,
        };

        return $message === null ? null : SmsText::normalize($message);
    }

    private function ticketsLabel(?int $tickets): string
    {
        return $tickets !== null && $tickets > 1 ? "{$tickets} billets. " : '';
    }
}
