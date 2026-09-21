<?php

namespace App\Notifications\Fiscalization;

use App\Modules\Fiscalization\Models\FiscalInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class CreditNoteStatusNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly FiscalInvoice $creditNote,
        private readonly string $event,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'fiscal_credit_note',
            'event' => $this->event,
            'invoiceUuid' => $this->creditNote->uuid,
            'reference' => $this->creditNote->kora_reference,
            'message' => $this->message(),
        ];
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
