<?php

namespace App\Modules\Ticketing\Http\Resources;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ressource enrichie pour l'écran « Mes voyages » de l'app passager.
 *
 * Contrairement à [TicketResource], qui sert `GET bookings/{uuid}/tickets` avec
 * un contrat minimal (segments à plat), celle-ci agrège les informations que la
 * maquette affiche sur la carte de billet et l'écran de détail : route, date/heure
 * de départ, siège, gare de départ, montant payé, QR code et statut mappé vers
 * le vocabulaire de l'app (confirmed / completed / cancelled).
 *
 * @mixin Ticket
 */
class PassengerTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $passenger = $this->reservationPassenger;
        $segments = $passenger === null ? collect() : $passenger->segments->sortBy('leg_order');
        $firstSegment = $segments->first();
        $lastSegment = $segments->last();

        $reservation = $this->reservation;
        $departure = $firstSegment?->departure;
        $line = $departure?->line;
        $seat = $firstSegment?->seat;
        $originStop = $firstSegment?->originStop;
        $destinationStop = $lastSegment?->destinationStop;

        return [
            'uuid' => $this->uuid,
            'short_code' => $this->short_code,
            'qr_code' => $this->qr_code,
            'status' => $this->mapStatus($this->status),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'reservation_uuid' => $reservation?->uuid,
            'amount' => $reservation !== null ? (int) $reservation->total_amount : 0,
            'currency' => $reservation !== null ? $reservation->currency : 'XOF',
            'passenger' => $passenger === null ? null : [
                'uuid' => $passenger->uuid,
                'first_name' => $passenger->first_name,
                'last_name' => $passenger->last_name,
            ],
            'origin' => $originStop === null ? null : [
                'city' => $originStop->city,
                'city_code' => $this->cityCode($originStop),
                'stop_name' => $originStop->name,
            ],
            'destination' => $destinationStop === null ? null : [
                'city' => $destinationStop->city,
                'city_code' => $this->cityCode($destinationStop),
                'stop_name' => $destinationStop->name,
            ],
            'departure' => $departure === null ? null : [
                'uuid' => $departure->uuid,
                'departs_at' => $departure->scheduledDepartureAt()->toIso8601String(),
                'line_name' => $line?->name,
                'line_code' => $line?->code,
            ],
            'seat' => $seat === null ? null : [
                'uuid' => $seat->uuid,
                'code' => $seat->code,
            ],
        ];
    }

    /**
     * Mappe le statut backend vers le vocabulaire de l'app passager.
     *
     * - `issued` → `confirmed` (billet valide, départ à venir)
     * - `used` → `completed` (voyage effectué)
     * - `cancelled` / `refunded` → `cancelled`
     */
    private function mapStatus(TicketStatus $status): string
    {
        return match ($status) {
            TicketStatus::Issued => 'confirmed',
            TicketStatus::Used => 'completed',
            TicketStatus::Cancelled, TicketStatus::Refunded => 'cancelled',
        };
    }

    /**
     * Code ville de 3 lettres affiché sur le billet (CTN, MLV…).
     *
     * `PhysicalStop` porte un `code` ; `Stop` non. On tente d'abord le code de
     * l'arrêt physique, puis on dérive du nom de ville en dernier recours.
     */
    private function cityCode(Stop $stop): string
    {
        $physicalStop = $stop->physicalStop;
        if ($physicalStop !== null && $physicalStop->code !== null && $physicalStop->code !== '') {
            return $physicalStop->code;
        }

        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $stop->city ?? ''));

        return $letters === '' ? '---' : substr($letters, 0, 3);
    }
}
