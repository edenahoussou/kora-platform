<?php

namespace App\Modules\Ticketing\Actions;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Ticketing\Models\Ticket;

/**
 * Builds the signed QR payload for a ticket using Ed25519.
 *
 * The payload is a compact JSON document containing the essential boarding
 * information (passenger, legs, seat, agency) plus an Ed25519 signature.
 * The resulting token follows a JWT-like `payload.signature` format encoded
 * as base64url, so the KORA P501 boarding app can validate tickets offline
 * using only the public key — no shared secret required.
 */
final class BuildTicketQrPayloadAction
{
    /**
     * Build the signed QR token for the given ticket.
     *
     * @param  Reservation  $reservation  Owning reservation (used for agency + channel).
     * @param  Ticket  $ticket  The ticket to encode.
     * @return string The `base64url(payload).base64url(signature)` token.
     */
    public function execute(Reservation $reservation, Ticket $ticket): string
    {
        $passenger = $ticket->reservationPassenger;
        $segments = $passenger?->segments?->sortBy('leg_order') ?? collect();

        $legs = $segments->map(fn ($segment): array => [
            'd' => $segment->direction ?? 'outbound',
            'o' => $segment->originStop->city ?? $segment->originStop->name,
            'x' => $segment->destinationStop->city ?? $segment->destinationStop->name,
            'dt' => $segment->departure?->departure_date?->toDateString(),
            'st' => $segment->seat?->code,
            'ln' => $segment->departure?->line?->code,
        ])->values()->all();

        $payload = [
            'v' => 2, // Ed25519 signature (v1 was HMAC-SHA256)
            't' => $ticket->uuid,
            's' => $ticket->short_code,
            'p' => $passenger !== null
                ? trim($passenger->first_name.' '.$passenger->last_name)
                : null,
            'l' => $legs,
            'i' => $ticket->issued_at?->toIso8601String(),
            'a' => $this->agencyCode($reservation),
        ];

        $payloadJson = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = sodium_crypto_sign_detached($payloadJson, $this->signingPrivateKey());

        return $this->base64UrlEncode($payloadJson).'.'.$this->base64UrlEncode((string) $signature);
    }

    private function agencyCode(Reservation $reservation): ?string
    {
        $payment = $reservation->payments->sortByDesc('id')->first();
        $agent = $payment?->verifier;
        $agency = $agent->agency ?? $reservation->creator?->agency;

        return $agency?->code;
    }

    private function signingPrivateKey(): string
    {
        $key = config('tickets.signing_private_key');

        if ($key === null || $key === '') {
            throw new \RuntimeException(
                'KORA_TICKET_SIGNING_PRIVATE_KEY is not configured. Set it in .env to issue verifiable ticket QR codes.',
            );
        }

        $decoded = base64_decode((string) $key, true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \RuntimeException(
                'KORA_TICKET_SIGNING_PRIVATE_KEY is invalid. Expected a base64-encoded '.SODIUM_CRYPTO_SIGN_SECRETKEYBYTES.'-byte Ed25519 secret key.',
            );
        }

        return $decoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
