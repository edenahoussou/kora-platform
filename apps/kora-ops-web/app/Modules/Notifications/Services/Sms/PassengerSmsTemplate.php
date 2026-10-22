<?php

namespace App\Modules\Notifications\Services\Sms;

use App\Modules\Booking\Models\Reservation;

final class PassengerSmsTemplate
{
    /** @param array{route?: string|null, scheduled_at?: string|null, eta?: string|null, short_code?: string|null, tickets?: int|null} $context */
    public function build(string $type, Reservation $reservation, array $context = []): ?string
    {
        $route = filled($context['route'] ?? null) ? (string) $context['route'] : 'votre trajet';
        $scheduledAt = filled($context['scheduled_at'] ?? null) ? ' du '.$context['scheduled_at'] : '';
        $eta = filled($context['eta'] ?? null) ? (string) $context['eta'] : null;

        $message = match ($type) {
            'reservation_confirmed' => "KORA BUS : trajet {$route} confirmé. "
                .$this->ticketsLabel($context['short_code'] ?? null, $context['tickets'] ?? null)
                .'Détails dans l’application.',
            'departure_cancelled' => "KORA BUS : votre départ {$route}{$scheduledAt} est annulé. Consultez votre réservation.",
            'departure_delayed' => $eta === null
                ? "KORA BUS : votre départ {$route} est retardé. Consultez votre billet pour l’horaire à jour."
                : "KORA BUS : votre départ {$route} est retardé. Nouvel horaire estimé : {$eta}. Consultez votre billet.",
            default => null,
        };

        return $message === null ? null : SmsText::normalize($message);
    }

    private function ticketsLabel(?string $shortCode, ?int $tickets): string
    {
        if ($tickets !== null && $tickets > 1) {
            return "{$tickets} billets. ";
        }

        return filled($shortCode) ? "Billet {$shortCode}. " : '';
    }
}
