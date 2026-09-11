<?php

namespace App\Jobs;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Notifications\Data\WhatsAppDeliveryStatus;
use App\Modules\Notifications\Models\WhatsAppDelivery;
use App\Modules\Notifications\Services\WhatsApp\GenukaWhatsAppSender;
use App\Modules\Notifications\Services\WhatsApp\WahaWhatsAppSender;
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

class SendReservationWhatsAppNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 90, 300];

    public function __construct(
        public readonly int $reservationId,
        public readonly int $ticketId,
        public readonly ?string $phoneOverride = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GenukaWhatsAppSender $genuka, WahaWhatsAppSender $waha, GenerateTicketPdfAction $generatePdf): void
    {
        $reservation = Reservation::query()->with(['tickets.reservationPassenger', 'payments', 'payer', 'companyAccount'])->findOrFail($this->reservationId);
        $ticket = $reservation->tickets->firstWhere('id', $this->ticketId);
        if ($ticket === null) {
            return;
        }
        $payment = $reservation->payments->firstWhere('status', 'confirmed') ?? $reservation->payments->first();
        $defaultPhone = $payment->payer_phone ?? ($reservation->payer_type->value === 'company' ? $reservation->companyAccount?->phone : $reservation->payer?->phone);
        $phone = $this->phoneOverride ?? $defaultPhone;

        $delivery = WhatsAppDelivery::query()->firstOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'uuid' => (string) Str::uuid(),
                'reservation_id' => $reservation->id,
                'provider' => 'genuka',
                'recipient_phone' => (string) $phone,
                'status' => WhatsAppDeliveryStatus::Pending,
            ],
        );

        if ($this->phoneOverride !== null && $delivery->recipient_phone !== $this->phoneOverride) {
            $delivery->update(['recipient_phone' => $this->phoneOverride, 'status' => WhatsAppDeliveryStatus::Pending, 'last_error' => null]);
        }

        if ($phone === null || trim($phone) === '') {
            $delivery->update(['status' => WhatsAppDeliveryStatus::Skipped, 'last_error' => 'Aucun numéro WhatsApp valide pour le payeur.']);

            return;
        }

        if ($delivery->getRawOriginal('status') === WhatsAppDeliveryStatus::Sent->value) {
            return;
        }

        $text = "KORA BUS — billet {$ticket->short_code}\nPassager : {$ticket->reservationPassenger?->first_name} {$ticket->reservationPassenger?->last_name}\nRéservation : {$reservation->uuid}\nMontant payé : {$reservation->total_amount} XOF";

        $pdf = $generatePdf->execute($ticket);
        $documentData = base64_encode($pdf['content']);
        $documentFilename = $pdf['filename'];

        $delivery->increment('attempts');

        try {
            $result = $genuka->send($phone, $text, $documentData, $documentFilename);
            $delivery->update(['provider' => 'genuka', 'status' => WhatsAppDeliveryStatus::Sent, 'provider_message_id' => $result['message_id'], 'sent_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            if (! $this->shouldFallback($exception)) {
                $delivery->update(['status' => WhatsAppDeliveryStatus::Failed, 'last_error' => $exception->getMessage()]);
                throw $exception;
            }

            try {
                $result = $waha->send($phone, $text, $documentData, $documentFilename);
                $delivery->update(['provider' => 'waha', 'status' => WhatsAppDeliveryStatus::Sent, 'provider_message_id' => $result['message_id'], 'sent_at' => now(), 'last_error' => $exception->getMessage()]);
            } catch (Throwable $fallbackException) {
                $delivery->update(['provider' => 'waha', 'status' => WhatsAppDeliveryStatus::Failed, 'last_error' => $fallbackException->getMessage()]);
                throw $fallbackException;
            }
        }
    }

    private function shouldFallback(Throwable $exception): bool
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return $status === null || $status >= 500 || $status === 408 || $status === 429;
    }
}
