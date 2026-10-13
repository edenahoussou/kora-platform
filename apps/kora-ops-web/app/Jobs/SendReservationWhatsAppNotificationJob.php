<?php

namespace App\Jobs;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Notifications\Data\WhatsAppDeliveryStatus;
use App\Modules\Notifications\Models\WhatsAppDelivery;
use App\Modules\Notifications\Services\WhatsApp\InvalidWhatsAppNumberException;
use App\Modules\Notifications\Services\WhatsApp\WahaReachoutBlockedException;
use App\Modules\Notifications\Services\WhatsApp\WhatsAppMessageDispatcher;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
    public function handle(WhatsAppMessageDispatcher $whatsapp, GenerateTicketPdfAction $generatePdf): void
    {
        try {
            Cache::lock("whatsapp-send-{$this->ticketId}", 300)
                ->block(0, fn () => $this->send($whatsapp, $generatePdf));
        } catch (LockTimeoutException) {
            return;
        }
    }

    private function send(WhatsAppMessageDispatcher $whatsapp, GenerateTicketPdfAction $generatePdf): void
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

        try {
            $result = $whatsapp->send($phone, $text, $documentData, $documentFilename);
            $delivery->update(['provider' => $result['provider'], 'status' => WhatsAppDeliveryStatus::Sent, 'provider_message_id' => $result['message_id'], 'sent_at' => now(), 'last_error' => $result['fallback_error']]);
        } catch (InvalidWhatsAppNumberException $invalidNumberException) {
            $delivery->update([
                'provider' => 'waha',
                'status' => WhatsAppDeliveryStatus::InvalidNumber,
                'last_error' => $invalidNumberException->getMessage(),
            ]);
        } catch (WahaReachoutBlockedException $blockedException) {
            $delivery->update([
                'provider' => 'waha',
                'status' => WhatsAppDeliveryStatus::Skipped,
                'last_error' => $blockedException->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $delivery->update(['provider' => 'waha', 'status' => WhatsAppDeliveryStatus::Failed, 'last_error' => $exception->getMessage()]);
            throw $exception;
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
