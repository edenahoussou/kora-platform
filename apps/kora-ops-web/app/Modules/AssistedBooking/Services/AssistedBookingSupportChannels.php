<?php

namespace App\Modules\AssistedBooking\Services;

use App\Modules\AssistedBooking\Data\AssistedBookingChannel;
use App\Modules\AssistedBooking\Exceptions\AssistedBookingSupportUnavailableException;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\Settings\Settings\GeneralSettings;

class AssistedBookingSupportChannels
{
    public function ensureAvailable(AssistedBookingChannel $channel): string
    {
        $phone = $this->phone($channel);

        if ($phone === null) {
            throw new AssistedBookingSupportUnavailableException($channel);
        }

        return $phone;
    }

    /** @return array{call_phone: string|null, whatsapp_phone: string|null, call_url: string|null, whatsapp_url: string|null, whatsapp_message: string} */
    public function forBooking(AssistedBooking $assistedBooking): array
    {
        $callPhone = $this->phone(AssistedBookingChannel::Call);
        $whatsappPhone = $this->phone(AssistedBookingChannel::WhatsApp);

        return [
            'call_phone' => $callPhone,
            'whatsapp_phone' => $whatsappPhone,
            'call_url' => $callPhone ? 'tel:'.$callPhone : null,
            'whatsapp_url' => $whatsappPhone
                ? 'https://wa.me/'.preg_replace('/\D+/', '', $whatsappPhone).'?text='.rawurlencode($this->whatsappMessage($assistedBooking))
                : null,
            'whatsapp_message' => $this->whatsappMessage($assistedBooking),
        ];
    }

    public function whatsappMessage(AssistedBooking $assistedBooking): string
    {
        $snapshot = $assistedBooking->snapshot;
        $legs = array_map(
            fn (array $leg): string => $leg['route'].', '.$leg['departure'],
            $this->itinerary($assistedBooking),
        );

        $seatCodes = [];
        foreach ($snapshot['requested_seats'] ?? [] as $seat) {
            if (is_array($seat) && is_string($seat['code'] ?? null)) {
                $seatCodes[] = $seat['code'];
            }
        }

        $seats = implode(', ', array_unique($seatCodes));
        $validUntil = $assistedBooking->expires_at->timezone(app(GeneralSettings::class)->timezone)->format('d/m/Y H:i');

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

    /** @return list<array{route: string, departure: string, seats: list<string>}> */
    public function itinerary(AssistedBooking $assistedBooking): array
    {
        $snapshot = $assistedBooking->snapshot;
        $timezone = app(GeneralSettings::class)->timezone;
        $requestedSeats = [];
        foreach ($snapshot['requested_seats'] ?? [] as $seat) {
            if (is_array($seat) && isset($seat['leg_order']) && is_string($seat['code'] ?? null)) {
                $requestedSeats[$seat['leg_order']][] = $seat['code'];
            }
        }

        $legs = [];
        foreach ($snapshot['legs'] ?? [] as $leg) {
            if (! is_array($leg)) {
                continue;
            }

            $legs[] = [
                'route' => sprintf('%s → %s', $leg['origin'] ?? '?', $leg['destination'] ?? '?'),
                'departure' => isset($leg['departure_at'])
                    ? now()->parse($leg['departure_at'])->timezone($timezone)->format('d/m/Y H:i')
                    : 'horaire à confirmer',
                'seats' => array_values(array_unique($requestedSeats[$leg['leg_order'] ?? null] ?? [])),
            ];
        }

        return $legs;
    }

    private function phone(AssistedBookingChannel $channel): ?string
    {
        $settings = app(GeneralSettings::class);
        $value = $channel === AssistedBookingChannel::WhatsApp
            ? $settings->supportWhatsappPhone
            : $settings->supportCallPhone;

        if (! is_string($value)) {
            return null;
        }

        $phone = preg_replace('/[\s().-]+/', '', trim($value));
        if ($phone === null) {
            return null;
        }
        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }

        return preg_match('/^\+[1-9][0-9]{7,14}$/', $phone) === 1 ? $phone : null;
    }
}
