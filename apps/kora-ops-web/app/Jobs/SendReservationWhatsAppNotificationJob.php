<?php

namespace App\Jobs;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Notifications\Models\WhatsAppDelivery;
use App\Modules\Notifications\Services\WhatsApp\GenukaWhatsAppSender;
use App\Modules\Notifications\Services\WhatsApp\WahaWhatsAppSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class SendReservationWhatsAppNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 90, 300];

    public function __construct(public readonly int $reservationId, public readonly int $ticketId) {}

    /**
     * Execute the job.
     */
    public function handle(GenukaWhatsAppSender $genuka, WahaWhatsAppSender $waha): void
    {
        $reservation = Reservation::query()->with(['tickets.reservationPassenger', 'payments', 'payer', 'companyAccount'])->findOrFail($this->reservationId);
        $ticket = $reservation->tickets->firstWhere('id', $this->ticketId);
        if ($ticket === null) {
            return;
        }
        $payment = $reservation->payments->firstWhere('status', 'confirmed') ?? $reservation->payments->first();
        $phone = $payment->payer_phone ?? ($reservation->payer_type->value === 'company' ? $reservation->companyAccount?->phone : $reservation->payer?->phone);
        $delivery = WhatsAppDelivery::query()->firstOrCreate(
            ['ticket_id' => $ticket->id, 'provider' => 'genuka'],
            ['uuid' => (string) Str::uuid(), 'reservation_id' => $reservation->id, 'recipient_phone' => (string) $phone, 'status' => 'pending'],
        );
        if ($phone === null || trim($phone) === '') {
            $delivery->update(['status' => 'skipped', 'last_error' => 'Aucun numéro WhatsApp valide pour le payeur.']);

            return;
        }
        $text = "KORA BUS — billet {$ticket->short_code}\nPassager : {$ticket->reservationPassenger?->first_name} {$ticket->reservationPassenger?->last_name}\nRéservation : {$reservation->uuid}\nMontant payé : {$reservation->total_amount} XOF";
        $documentUrl = URL::temporarySignedRoute('tickets.signed-pdf', now()->addDay(), ['ticket' => $ticket]);
        try {
            $delivery->increment('attempts');
            $result = $genuka->send($phone, $text, $documentUrl);
            $delivery->update(['status' => 'sent', 'provider_message_id' => $result['message_id'], 'sent_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            if ($this->shouldFallback($exception)) {
                $delivery->update(['provider' => 'waha']);
                $result = $waha->send($phone, $text, $documentUrl);
                $delivery->update(['status' => 'sent', 'provider_message_id' => $result['message_id'], 'sent_at' => now(), 'last_error' => null]);

                return;
            }
            $delivery->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function shouldFallback(Throwable $exception): bool
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return $status === null || $status >= 500 || $status === 408 || $status === 429;
    }
}
