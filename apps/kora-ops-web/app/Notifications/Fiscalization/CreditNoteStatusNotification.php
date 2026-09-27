<?php

namespace App\Notifications\Fiscalization;

use App\Modules\Fiscalization\Models\FiscalInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CreditNoteStatusNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly FiscalInvoice $creditNote,
        private readonly string $event,
    ) {
        $this->id = (string) Str::uuid();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['opsDatabase'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'audience' => 'ops',
            'event_uuid' => 'credit-note:'.$this->creditNote->uuid.':'.$this->event,
            'kind' => 'fiscal_credit_note',
            'type' => 'fiscal_credit_note',
            'severity' => 'info',
            'title' => $this->title(),
            'event' => $this->event,
            'invoiceUuid' => $this->creditNote->uuid,
            'reference' => $this->creditNote->kora_reference,
            'message' => $this->message(),
            'occurred_at' => now()->toISOString(),
            'data' => [
                'invoice_uuid' => $this->creditNote->uuid,
                'reference' => $this->creditNote->kora_reference,
            ],
        ];
    }

    private function title(): string
    {
        return match ($this->event) {
            'processing' => 'Facture d’avoir en cours',
            'certified' => 'Facture d’avoir certifiée',
            'failed' => 'Échec de facture d’avoir',
            default => 'Mise à jour de facture d’avoir',
        };
    }

    private function message(): string
    {
        return match ($this->event) {
            'processing' => 'L’émission de la facture d’avoir est en cours.',
            'certified' => 'La facture d’avoir a été certifiée par e‑MECeF.',
            'failed' => 'L’émission de la facture d’avoir a échoué.',
            default => 'La facture d’avoir a été mise à jour.',
        };
    }
}
