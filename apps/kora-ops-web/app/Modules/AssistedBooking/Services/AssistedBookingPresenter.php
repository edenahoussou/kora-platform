<?php

namespace App\Modules\AssistedBooking\Services;

use App\Modules\AssistedBooking\Models\AssistedBooking;

class AssistedBookingPresenter
{
    public function whatsappMessage(AssistedBooking $assistedBooking): string
    {
        $snapshot = $assistedBooking->snapshot;
        $legs = [];
        foreach ($snapshot['legs'] ?? [] as $leg) {
            if (! is_array($leg)) {
                continue;
            }
            $departureAt = isset($leg['departure_at'])
                ? now()->parse($leg['departure_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i')
                : 'horaire à confirmer';

            $legs[] = sprintf('%s → %s, %s', $leg['origin'] ?? '?', $leg['destination'] ?? '?', $departureAt);
        }
        $seatCodes = [];
        foreach ($snapshot['requested_seats'] ?? [] as $seat) {
            if (is_array($seat) && is_string($seat['code'] ?? null)) {
                $seatCodes[] = $seat['code'];
            }
        }
        $seats = implode(', ', array_unique($seatCodes));
        $validUntil = $assistedBooking->expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i');

        return implode("\n", [
            'Bonjour, je souhaite terminer cette réservation KORA :',
            'Référence : '.$assistedBooking->reservation->reference,
            'Trajet : '.implode(' / ', $legs),
            'Sièges souhaités : '.($seats !== '' ? $seats : 'à choisir'),
            'Passagers : '.(int) ($snapshot['passenger_count'] ?? 0),
            'Montant indicatif : '.number_format($assistedBooking->original_total_amount, 0, ',', ' ').' '.$assistedBooking->currency,
            'Valable jusqu’au : '.$validUntil,
            'Contact : '.$assistedBooking->contact_name.' — '.$assistedBooking->contact_phone,
        ]);
    }

    public function whatsappUrl(AssistedBooking $assistedBooking): ?string
    {
        $phone = $assistedBooking->preferredAgency->whatsapp_phone;
        if (! $phone) {
            return null;
        }

        return 'https://wa.me/'.preg_replace('/\D+/', '', $phone).'?text='.rawurlencode($this->whatsappMessage($assistedBooking));
    }

    public function callUrl(AssistedBooking $assistedBooking): ?string
    {
        $phone = $assistedBooking->preferredAgency->call_phone;

        return $phone ? 'tel:'.$phone : null;
    }
}
