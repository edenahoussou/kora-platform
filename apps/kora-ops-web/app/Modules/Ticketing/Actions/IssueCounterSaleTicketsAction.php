<?php

namespace App\Modules\Ticketing\Actions;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class IssueCounterSaleTicketsAction
{
    public function __construct(
        private readonly BuildTicketQrPayloadAction $buildQrPayload,
    ) {}

    /**
     * @return Collection<int, Ticket>
     */
    public function execute(Reservation $reservation): Collection
    {
        $tickets = new Collection;

        foreach ($reservation->passengers()->where('status', 'active')->orderBy('passenger_index')->orderBy('id')->get() as $passenger) {
            $ticket = Ticket::query()->firstOrCreate(
                ['reservation_passenger_id' => $passenger->getKey()],
                [
                    'reservation_id' => $reservation->getKey(),
                    'qr_code' => (string) Str::uuid(),
                    'short_code' => $this->shortCode(),
                    'status' => TicketStatus::Issued,
                    'issued_at' => now(),
                ],
            );

            if ($ticket->wasRecentlyCreated) {
                $ticket->forceFill([
                    'qr_code' => $this->buildQrPayload->execute($reservation, $ticket),
                ])->save();
            }

            $tickets->push($ticket);
        }

        return $tickets;
    }

    private function shortCode(): string
    {
        return 'K'.Str::upper(Str::random(7));
    }
}
