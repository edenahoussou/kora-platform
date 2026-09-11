<?php

namespace App\Jobs;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Notifications\Data\WhatsAppDeliveryStatus;
use App\Modules\Notifications\Models\WhatsAppDelivery;
use App\Modules\Notifications\Services\WhatsApp\GenukaWhatsAppSender;
use App\Modules\Notifications\Services\WhatsApp\WahaReachoutBlockedException;
use App\Modules\Notifications\Services\WhatsApp\WahaWhatsAppSender;
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use App\Modules\Ticketing\Models\Ticket;
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

        $text = $this->buildMessage($reservation, $ticket);

        // Délai anti-blocage WAHA pour les contacts nouveaux.
        $this->throttleIfNewContact((string) $phone);

        $pdf = $generatePdf->execute($ticket);
        $documentData = base64_encode($pdf['content']);
        $documentFilename = $pdf['filename'];

        $delivery->increment('attempts');

        // Genuka primaire, fallback WAHA si non configuré.
        if ($this->isGenukaConfigured()) {
            try {
                $result = $genuka->send($phone, $text, $documentData, $documentFilename);
                $delivery->update(['provider' => 'genuka', 'status' => WhatsAppDeliveryStatus::Sent, 'provider_message_id' => $result['message_id'], 'sent_at' => now(), 'last_error' => null]);

                return;
            } catch (Throwable $exception) {
                if (! $this->shouldFallback($exception)) {
                    $delivery->update(['status' => WhatsAppDeliveryStatus::Failed, 'last_error' => $exception->getMessage()]);
                    throw $exception;
                }
                $genukaError = $exception->getMessage();
            }
        } else {
            $genukaError = 'Genuka non configuré (GENUKA_TOKEN manquant) — bascule directe vers WAHA.';
        }

        try {
            $result = $waha->send($phone, $text, $documentData, $documentFilename);
            $delivery->update(['provider' => 'waha', 'status' => WhatsAppDeliveryStatus::Sent, 'provider_message_id' => $result['message_id'], 'sent_at' => now(), 'last_error' => $genukaError]);
        } catch (WahaReachoutBlockedException $blockedException) {
            $delivery->update([
                'provider' => 'waha',
                'status' => WhatsAppDeliveryStatus::Skipped,
                'last_error' => $blockedException->getMessage(),
            ]);
        } catch (Throwable $fallbackException) {
            $delivery->update(['provider' => 'waha', 'status' => WhatsAppDeliveryStatus::Failed, 'last_error' => $fallbackException->getMessage()]);
            throw $fallbackException;
        }
    }

    private function buildMessage(Reservation $reservation, Ticket $ticket): string
    {
        $firstName = trim((string) $ticket->reservationPassenger?->first_name);
        $fullName = trim($ticket->reservationPassenger?->first_name.' '.$ticket->reservationPassenger?->last_name);
        $greeting = $firstName !== '' ? "Bonjour {$firstName}," : 'Bonjour,';

        $lines = [
            $greeting,
            "votre billet KORA BUS {$ticket->short_code} est confirmé.",
            "Passager : {$fullName}",
            "Réservation : {$reservation->uuid}",
            "Montant payé : {$reservation->total_amount} XOF",
            'Merci de voyager avec KORA BUS.',
        ];

        return collect($lines)
            ->map(fn (string $line): string => $this->jitterSpaces($line))
            ->implode("\n");
    }

    private function jitterSpaces(string $line): string
    {
        if (random_int(1, 100) <= 30) {
            $line = preg_replace('/([,:]) /', '$1  ', $line, 1) ?? $line;
        }

        return $line;
    }

    private function isGenukaConfigured(): bool
    {
        $token = (string) config('services.genuka.token');
        $connectionId = (string) config('services.genuka.connection_id');

        return $token !== '' && $connectionId !== '';
    }

    private function shouldFallback(Throwable $exception): bool
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return $status === null || $status === 401 || $status === 403 || $status === 408 || $status === 429 || $status >= 500;
    }

    private function throttleIfNewContact(string $phone): void
    {
        $delaySeconds = (int) config('services.waha.new_contact_delay_seconds', 30);

        if ($delaySeconds <= 0) {
            return;
        }

        $alreadyContacted = WhatsAppDelivery::query()
            ->where('recipient_phone', $phone)
            ->where('status', WhatsAppDeliveryStatus::Sent->value)
            ->where('sent_at', '>=', now()->subDay())
            ->exists();

        if (! $alreadyContacted) {
            sleep(random_int($delaySeconds, $delaySeconds * 2));
        }
    }
}
