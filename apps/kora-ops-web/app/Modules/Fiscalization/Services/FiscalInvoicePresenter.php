<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Fiscalization\Data\FiscalInvoiceDocumentKind;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Models\FiscalSyncAttempt;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Codianselme\LaraSygmef\Models\EmecfInvoiceItem;
use Codianselme\LaraSygmef\Models\EmecfInvoicePayment;
use Throwable;

class FiscalInvoicePresenter
{
    /** @return array<string, mixed> */
    public function listItem(FiscalInvoice $invoice): array
    {
        $emecf = $invoice->emecfInvoice;
        $type = $this->type($invoice);
        $reference = $invoice->kora_reference;
        $mecefUid = $invoice->mecef_uid;
        $clientName = data_get($invoice->getRelationValue('payment'), 'payer_name')
            ?? data_get($invoice->getRelationValue('reservation'), 'payer_snapshot.name');
        $clientContact = data_get($invoice->getRelationValue('payment'), 'payer_phone')
            ?? data_get($invoice->getRelationValue('reservation'), 'payer_snapshot.phone');
        $finalizedAt = null;

        if ($emecf !== null) {
            $reference = $emecf->code_mec_ef_dgi ?: $reference;
            $mecefUid = $emecf->uid;
            $clientName = $emecf->client_name ?: $clientName;
            $clientContact = $emecf->client_contact ?: $clientContact;
            $finalizedAt = $emecf->finalized_at?->toIso8601String();
        }

        return [
            'uuid' => $invoice->uuid,
            'reference' => $reference,
            'koraReference' => $invoice->kora_reference,
            'mecefUid' => $mecefUid,
            'documentKind' => $invoice->document_kind->value,
            'documentType' => $type['code'],
            'documentTypeLabel' => $type['label'],
            'reservationReference' => $invoice->reservation?->reference,
            'reservationUuid' => $invoice->reservation?->uuid,
            'client' => [
                'name' => $clientName,
                'contact' => $clientContact,
            ],
            'status' => $invoice->status->value,
            'statusLabel' => $invoice->status->label(),
            'mecefStatus' => $emecf?->status->value,
            'lastError' => $invoice->last_error,
            'amount' => $emecf !== null ? (int) $emecf->total : $invoice->total_with_tax,
            'currency' => $invoice->reservation->currency,
            'attemptCount' => $invoice->syncAttempts_count
                ?? ($invoice->relationLoaded('syncAttempts') ? $invoice->syncAttempts->count() : 0),
            'creditNotesCount' => $invoice->credit_notes_count ?? 0,
            'createdAt' => $invoice->created_at?->toIso8601String(),
            'finalizedAt' => $finalizedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(FiscalInvoice $invoice, bool $canViewPayload): array
    {
        $invoice->loadMissing([
            'reservation',
            'payment',
            'emecfInvoice.items',
            'emecfInvoice.payments',
            'syncAttempts',
            'originalFiscalInvoice.emecfInvoice',
            'creditNotes.emecfInvoice.items',
            'requestedBy',
        ]);

        $itemLines = $invoice->emecfInvoice !== null ? $invoice->emecfInvoice->items : collect();

        return array_merge($this->listItem($invoice), [
            'fiscal' => $this->fiscalData($invoice->emecfInvoice),
            'emitter' => $this->emitter(),
            'items' => $itemLines->values()->map(fn (EmecfInvoiceItem $item, int $index): array => [
                'lineKey' => 'line-'.$index,
                'code' => $item->code,
                'name' => $item->name,
                'price' => (int) $item->price,
                'quantity' => (float) $item->quantity,
                'taxGroup' => $item->tax_group->value,
                'taxSpecific' => $item->tax_specific,
                'originalPrice' => $item->original_price,
                'priceModification' => $item->price_modification,
                'creditedQuantity' => $this->creditedQuantity($invoice, $item, $index),
                'remainingQuantity' => max(0, abs((float) $item->quantity) - $this->creditedQuantity($invoice, $item, $index)),
            ])->all(),
            'payments' => ($invoice->emecfInvoice !== null ? $invoice->emecfInvoice->payments : collect())->map(fn (EmecfInvoicePayment $payment): array => [
                'name' => $payment->name->value,
                'label' => $payment->payment_type_label,
                'amount' => (int) $payment->amount,
            ])->all(),
            'attempts' => $invoice->syncAttempts->sortBy('attempt_number')->values()->map(fn (FiscalSyncAttempt $attempt): array => [
                'uuid' => $attempt->uuid,
                'operation' => $attempt->operation,
                'number' => $attempt->attempt_number,
                'status' => $attempt->status,
                'responseCode' => $attempt->response_code,
                'errorCode' => $attempt->error_code,
                'errorMessage' => $attempt->error_message,
                'summary' => $attempt->response_payload,
                'payload' => $canViewPayload ? $attempt->response_payload_encrypted : null,
                'attemptedAt' => $attempt->attempted_at->toIso8601String(),
                'completedAt' => $attempt->completed_at?->toIso8601String(),
            ])->all(),
            'creditNotes' => $invoice->creditNotes->map(fn (FiscalInvoice $creditNote): array => $this->listItem($creditNote))->all(),
            'originalInvoice' => $invoice->originalFiscalInvoice === null ? null : $this->listItem($invoice->originalFiscalInvoice),
            'reason' => $invoice->credit_note_reason,
            'requestedAt' => $invoice->requested_at?->toIso8601String(),
            'requestedBy' => $invoice->requestedBy?->name,
            'sourceSnapshot' => $canViewPayload ? $invoice->source_snapshot : null,
            'canViewPayload' => $canViewPayload,
        ]);
    }

    /** @return array<string, mixed> */
    private function fiscalData(?EmecfInvoice $invoice): array
    {
        if ($invoice === null) {
            return [];
        }

        return [
            'uid' => $invoice->uid,
            'ifu' => $invoice->ifu,
            'aib' => $invoice->aib,
            'type' => $invoice->type->value,
            'reference' => $invoice->reference,
            'operatorId' => $invoice->operator_id,
            'operatorName' => $invoice->operator_name,
            'clientIfu' => $invoice->client_ifu,
            'clientName' => $invoice->client_name,
            'clientContact' => $invoice->client_contact,
            'clientAddress' => $invoice->client_address,
            'taxes' => [
                'ta' => $invoice->ta,
                'tb' => $invoice->tb,
                'tc' => $invoice->tc,
                'td' => $invoice->td,
                'taa' => $invoice->taa,
                'tab' => $invoice->tab,
                'tac' => $invoice->tac,
                'tad' => $invoice->tad,
                'tae' => $invoice->tae,
                'taf' => $invoice->taf,
                'hab' => $invoice->hab,
                'had' => $invoice->had,
                'vab' => $invoice->vab,
                'vad' => $invoice->vad,
                'aibAmount' => $invoice->aib_amount,
                'ts' => $invoice->ts,
                'total' => $invoice->total,
            ],
            'status' => $invoice->status->value,
            'statusLabel' => $invoice->status->label(),
            'codeMecEfDgi' => $invoice->code_mec_ef_dgi,
            'qrCode' => $invoice->qr_code,
            'dateTime' => $invoice->date_time,
            'counters' => $invoice->counters,
            'nim' => $invoice->nim,
            'errorCode' => $invoice->error_code,
            'errorDescription' => $invoice->error_desc,
            'submittedAt' => $invoice->submitted_at?->toIso8601String(),
            'finalizedAt' => $invoice->finalized_at?->toIso8601String(),
        ];
    }

    /**
     * Taxpayer identity as registered at the DGI, from the integrations
     * settings — rendered on fiscal invoice views.
     *
     * @return array<string, string>
     */
    private function emitter(): array
    {
        try {
            $settings = app(IntegrationsSettings::class);
        } catch (Throwable) {
            return [];
        }

        return array_filter([
            'shopName' => $settings->emecfShopName,
            'rccm' => $settings->emecfRccm,
            'nim' => $settings->emecfNim,
            'address' => implode(', ', array_filter([
                $settings->emecfAddress1,
                $settings->emecfAddress2,
                $settings->emecfAddress3,
            ])) ?: null,
            'contact' => implode(' / ', array_filter([
                $settings->emecfContact1,
                $settings->emecfContact2,
                $settings->emecfContact3,
            ])) ?: null,
        ], static fn ($value): bool => filled($value));
    }

    /** @return array{code: string, label: string} */
    private function type(FiscalInvoice $invoice): array
    {
        $type = $invoice->emecfInvoice?->type;

        return [
            'code' => $type !== null
                ? $type->value
                : ($invoice->document_kind === FiscalInvoiceDocumentKind::CreditNote ? 'FA' : 'FV'),
            'label' => $type?->label() ?? $invoice->document_kind->label(),
        ];
    }

    private function creditedQuantity(FiscalInvoice $invoice, EmecfInvoiceItem $item, int $index): float
    {
        if ($invoice->document_kind === FiscalInvoiceDocumentKind::CreditNote) {
            return 0;
        }

        return (float) $invoice->creditNotes
            ->flatMap(fn (FiscalInvoice $creditNote) => $creditNote->emecfInvoice !== null ? $creditNote->emecfInvoice->items : collect())
            ->filter(fn (EmecfInvoiceItem $creditedItem): bool => $this->sameLine($creditedItem, $item))
            ->sum(fn (EmecfInvoiceItem $creditedItem): float => abs((float) $creditedItem->quantity));
    }

    private function sameLine(EmecfInvoiceItem $left, EmecfInvoiceItem $right): bool
    {
        if ($left->code !== null && $right->code !== null) {
            return $left->code === $right->code;
        }

        return $left->name === $right->name && $left->price === $right->price;
    }
}
