<?php

namespace App\Modules\Ticketing\Jobs;

use App\Jobs\FiscalizeReservationInvoiceJob;
use App\Jobs\SendReservationWhatsAppNotificationJob;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Ticketing\Actions\IssueCounterSaleTicketsAction;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class IssueReservationTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public readonly int $reservationId) {}

    public function handle(IssueCounterSaleTicketsAction $issueTickets): void
    {
        $reservation = Reservation::query()->find($this->reservationId);

        if ($reservation === null || $reservation->status === ReservationStatus::Ticketed) {
            return;
        }

        if ($reservation->status !== ReservationStatus::Paid) {
            return;
        }

        $ticketed = false;
        try {
            DB::transaction(function () use ($issueTickets, &$ticketed): void {
                $reservation = Reservation::query()
                    ->with(['passengers', 'segments', 'tickets'])
                    ->whereKey($this->reservationId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($reservation->status !== ReservationStatus::Paid ||
                    $reservation->ticket_issuance_status === TicketIssuanceStatus::Issued) {
                    return;
                }

                if ($reservation->passengers->isEmpty()) {
                    $reservation->update([
                        'ticket_issuance_status' => TicketIssuanceStatus::Failed,
                        'ticket_issuance_error' => 'Aucun passager n’est disponible pour émettre les billets.',
                    ]);

                    return;
                }

                $reservation->update([
                    'ticket_issuance_status' => TicketIssuanceStatus::Processing,
                    'ticket_issuance_started_at' => now(),
                    'ticket_issuance_error' => null,
                ]);

                $issueTickets->execute($reservation);

                $activePassengerCount = $reservation->passengers()->where('status', 'active')->count();
                $issuedTicketCount = $reservation->tickets()->whereHas(
                    'reservationPassenger',
                    fn ($query) => $query->where('status', 'active'),
                )->count();
                if ($activePassengerCount !== $issuedTicketCount) {
                    throw new \RuntimeException('Certains billets n’ont pas pu être émis.');
                }

                $reservation->update([
                    'status' => ReservationStatus::Ticketed,
                    'ticket_issuance_status' => TicketIssuanceStatus::Issued,
                    'ticket_issued_at' => now(),
                    'ticket_issuance_error' => null,
                ]);
                $reservation->segments()->update(['status' => ReservationSegmentStatus::Ticketed]);
                $ticketed = true;
            });
        } catch (Throwable $exception) {
            Reservation::query()->whereKey($this->reservationId)->update([
                'ticket_issuance_status' => TicketIssuanceStatus::Failed->value,
                'ticket_issuance_error' => $exception->getMessage(),
            ]);
            Log::error('Reservation ticket issuance failed.', [
                'reservation_id' => $this->reservationId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($ticketed) {
            $this->dispatchPostIssuanceJobs();
        }
    }

    /**
     * Dispatch fiscalization and WhatsApp jobs after tickets are issued.
     *
     * These are best-effort: if dispatch fails (e.g. queue unavailable in
     * sync mode), the tickets are already issued and the downstream jobs
     * can be retried manually. We log the error but do not re-throw.
     */
    private function dispatchPostIssuanceJobs(): void
    {
        try {
            $ticketedReservation = Reservation::query()
                ->with(['tickets'])
                ->findOrFail($this->reservationId);

            FiscalizeReservationInvoiceJob::dispatch($this->reservationId);

            foreach ($ticketedReservation->tickets as $ticket) {
                SendReservationWhatsAppNotificationJob::dispatch($this->reservationId, $ticket->id);
            }
        } catch (Throwable $exception) {
            Log::warning('Post-issuance job dispatch failed.', [
                'reservation_id' => $this->reservationId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
