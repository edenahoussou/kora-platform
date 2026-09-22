<?php

namespace App\Jobs;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Notifications\Data\WhatsAppDeliveryStatus;
use App\Modules\Notifications\Models\WhatsAppDelivery;
use App\Modules\Notifications\Services\WhatsApp\GenukaWhatsAppSender;
use App\Modules\Notifications\Services\WhatsApp\WahaReachoutBlockedException;
use App\Modules\Notifications\Services\WhatsApp\WahaWhatsAppSender;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class SendReservationWhatsAppNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

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
        try {
            Cache::lock("whatsapp-send-{$this->ticketId}", 300)
                ->block(0, fn () => $this->send($genuka, $waha, $generatePdf));
        } catch (LockTimeoutException) {
            return;
        }
    }

    private function send(GenukaWhatsAppSender $genuka, WahaWhatsAppSender $waha, GenerateTicketPdfAction $generatePdf): void
    {
        $reservation = Reservation::query()->with(['tickets.reservationPassenger', 'payments', 'payer', 'companyAccount'])->findOrFail($this->reservationId);
        $ticket = $reservation->tickets->firstWhere('id', $this->ticketId);
        if ($ticket === null) {
            return;
        }
        $payment = $reservation->payments->firstWhere('status', 'confirmed') ?? $reservation->payments->first();
        $defaultPhone = $payment->payer_phone ?? ($reservation->payer_type->value === 'company' ? $reservation->companyAccount?->phone : $reservation->payer?->phone);
        $phone = $this->phoneOverride ?? $defaultPhone;

        if ($phone === null || trim((string) $phone) === '') {
            $delivery = WhatsAppDelivery::query()->firstOrCreate(
                ['ticket_id' => $ticket->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'reservation_id' => $reservation->id,
                    'provider' => 'genuka',
                    'recipient_phone' => '',
                    'status' => WhatsAppDeliveryStatus::Skipped,
                    'last_error' => 'Aucun numéro WhatsApp valide pour le payeur.',
                ],
            );

            return;
        }

        $phone = $this->normalizePhone((string) $phone);

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

        if ($delivery->getRawOriginal('status') === WhatsAppDeliveryStatus::Sent->value) {
            return;
        }

        $text = $this->buildMessage($reservation, $ticket);

        $this->throttleIfNewContact((string) $phone);

        $pdf = $generatePdf->execute($ticket);
        $documentData = base64_encode($pdf['content']);
        $documentFilename = $pdf['filename'];

        $delivery->increment('attempts');

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
        $settings = app(IntegrationsSettings::class);
        $token = (string) ($settings->genukaToken ?? '');
        $connectionId = (string) ($settings->genukaConnectionId ?? '');

        return $token !== '' && $connectionId !== '';
    }

    private function shouldFallback(Throwable $exception): bool
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        return $status === null || $status === 401 || $status === 403 || $status === 408 || $status === 429 || $status >= 500;
    }

    private function throttleIfNewContact(string $phone): void
    {
        $delaySeconds = app(IntegrationsSettings::class)->wahaNewContactDelaySeconds;

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

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '22901') && strlen($digits) === 13) {
            $digits = '229'.substr($digits, 5);
        }

        return '+'.$digits;
    }
}
