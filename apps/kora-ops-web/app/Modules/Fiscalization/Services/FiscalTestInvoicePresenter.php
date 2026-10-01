<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalTestInvoiceRun;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Codianselme\LaraSygmef\Models\EmecfInvoiceItem;

class FiscalTestInvoicePresenter
{
    public function __construct(private readonly IntegrationsSettings $settings) {}

    /** @return array<string, mixed> */
    public function listItem(FiscalTestInvoiceRun $run): array
    {
        $invoice = $run->emecfInvoice;

        return [
            'uuid' => $run->uuid,
            'caseNumber' => $run->case_number,
            'status' => $run->status,
            'statusLabel' => FiscalInvoiceStatus::tryFrom((string) $run->status)?->label() ?? 'En attente',
            'reference' => $invoice?->code_mec_ef_dgi ?: $invoice?->uid,
            'mecefUid' => $invoice?->uid,
            'amount' => $invoice === null ? 0 : (int) $invoice->total,
            'lastError' => $run->last_error,
            'createdAt' => $run->created_at?->toIso8601String(),
            'canDownloadPdf' => $run->status === FiscalInvoiceStatus::Certified->value && $invoice?->qr_code !== null,
            'pdfUrl' => route('fiscal-test-invoices.pdf', $run->uuid),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(FiscalTestInvoiceRun $run): array
    {
        $run->loadMissing(['emecfInvoice.items', 'emecfInvoice.payments']);
        $invoice = $run->emecfInvoice;

        return [
            'uuid' => $run->uuid,
            'reference' => $invoice?->code_mec_ef_dgi ?: $invoice?->uid ?: 'TEST-'.$run->case_number,
            'koraReference' => 'DGI-TEST-'.$run->case_number.'-'.$run->uuid,
            'mecefUid' => $invoice?->uid,
            'documentKind' => $invoice?->type->value === 'FA' ? 'credit_note' : 'sale',
            'documentType' => $invoice?->type->value ?? 'FV',
            'documentTypeLabel' => $invoice?->type->label() ?? 'Facture de vente',
            'reservationReference' => null,
            'reservationUuid' => null,
            'client' => [
                'name' => $invoice === null
                    ? ($this->requestSnapshot($run)['client']['name'] ?? null)
                    : $invoice->client_name,
                'contact' => $invoice === null
                    ? ($this->requestSnapshot($run)['client']['contact'] ?? null)
                    : $invoice->client_contact,
            ],
            'status' => $run->status,
            'statusLabel' => FiscalInvoiceStatus::tryFrom((string) $run->status)?->label() ?? 'En attente',
            'mecefStatus' => $invoice?->status->value,
            'lastError' => $run->last_error,
            'amount' => $invoice === null ? 0 : (int) $invoice->total,
            'currency' => 'XOF',
            'attemptCount' => $invoice?->finalized_at !== null ? 2 : ($invoice === null ? 1 : 1),
            'creditNotesCount' => 0,
            'createdAt' => $run->created_at?->toIso8601String(),
            'finalizedAt' => $invoice?->finalized_at?->toIso8601String(),
            'fiscal' => $invoice === null ? $this->pendingFiscalData($run) : $this->fiscalData($invoice),
            'emitter' => $this->emitter(),
            'items' => $invoice !== null ? $invoice->items->values()->map(fn (EmecfInvoiceItem $item, int $index): array => [
                'lineKey' => 'line-'.$index,
                'code' => $item->code,
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => (float) $item->quantity,
                'taxGroup' => $item->tax_group->value,
                'taxSpecific' => $item->tax_specific,
                'originalPrice' => $item->original_price,
                'priceModification' => $item->price_modification,
                'creditedQuantity' => 0,
                'remainingQuantity' => abs((float) $item->quantity),
            ])->all() : $this->snapshotItems($run),
            'payments' => $invoice?->payments->map(fn ($payment): array => [
                'name' => $payment->name->value,
                'label' => $payment->payment_type_label,
                'amount' => (int) $payment->amount,
            ])->all() ?? [],
            'attempts' => [],
            'creditNotes' => [],
            'originalInvoice' => null,
            'reason' => null,
            'requestedAt' => $run->created_at?->toIso8601String(),
            'requestedBy' => $run->requestedBy?->name,
            'sourceSnapshot' => null,
            'canViewPayload' => false,
            'pdfUrl' => $invoice !== null && $run->status === FiscalInvoiceStatus::Certified->value
                ? route('fiscal-test-invoices.pdf', $run->uuid)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function pendingFiscalData(FiscalTestInvoiceRun $run): array
    {
        $payload = $this->requestSnapshot($run);
        $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];
        $operator = is_array($payload['operator'] ?? null) ? $payload['operator'] : [];

        return [
            'uid' => null,
            'ifu' => $payload['ifu'] ?? null,
            'aib' => $payload['aib'] ?? null,
            'type' => $payload['type'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'operatorId' => $operator['id'] ?? null,
            'operatorName' => $operator['name'] ?? null,
            'clientIfu' => $client['ifu'] ?? null,
            'clientName' => $client['name'] ?? null,
            'clientContact' => $client['contact'] ?? null,
            'clientAddress' => $client['address'] ?? null,
            'taxes' => [],
            'status' => null,
            'statusLabel' => null,
            'codeMecEfDgi' => null,
            'qrCode' => null,
            'dateTime' => null,
            'counters' => null,
            'nim' => null,
            'errorCode' => null,
            'errorDescription' => $run->last_error,
            'submittedAt' => null,
            'finalizedAt' => null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function snapshotItems(FiscalTestInvoiceRun $run): array
    {
        $payload = $this->requestSnapshot($run);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        $mappedItems = collect($items)->values()->map(static fn (array $item, int $index): array => [
            'lineKey' => 'line-'.$index,
            'code' => $item['code'] ?? null,
            'name' => $item['name'] ?? 'Article de test',
            'price' => $item['price'] ?? 0,
            'quantity' => (float) ($item['quantity'] ?? 0),
            'taxGroup' => $item['taxGroup'] ?? null,
            'taxSpecific' => $item['taxSpecific'] ?? null,
            'originalPrice' => $item['originalPrice'] ?? null,
            'priceModification' => $item['priceModification'] ?? null,
            'creditedQuantity' => 0,
            'remainingQuantity' => abs((float) ($item['quantity'] ?? 0)),
        ])->all();

        return array_values($mappedItems);
    }

    /** @return array<string, mixed> */
    private function requestSnapshot(FiscalTestInvoiceRun $run): array
    {
        $snapshot = $run->getAttribute('request_snapshot');

        return is_array($snapshot) ? $snapshot : [];
    }

    /** @return array<string, mixed> */
    private function fiscalData(EmecfInvoice $invoice): array
    {
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

    /** @return array<string, string> */
    private function emitter(): array
    {
        return array_filter([
            'shopName' => $this->settings->emecfShopName,
            'rccm' => $this->settings->emecfRccm,
            'nim' => $this->settings->emecfNim,
            'address' => implode(', ', array_filter([
                $this->settings->emecfAddress1,
                $this->settings->emecfAddress2,
                $this->settings->emecfAddress3,
            ])) ?: null,
            'contact' => implode(' / ', array_filter([
                $this->settings->emecfContact1,
                $this->settings->emecfContact2,
                $this->settings->emecfContact3,
            ])) ?: null,
        ], static fn (mixed $value): bool => filled($value));
    }
}
