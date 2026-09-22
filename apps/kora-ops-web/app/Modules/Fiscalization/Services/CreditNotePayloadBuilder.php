<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Fiscalization\Models\FiscalInvoice;
use Illuminate\Support\Arr;

class CreditNotePayloadBuilder
{
    /** @return array<string, mixed> */
    public function build(FiscalInvoice $creditNote): array
    {
        $creditNote->loadMissing([
            'originalFiscalInvoice.emecfInvoice.items',
            'originalFiscalInvoice.emecfInvoice',
            'requestedBy',
        ]);

        $original = $creditNote->originalFiscalInvoice;
        $emecf = $original?->emecfInvoice;

        if ($original === null || $emecf === null) {
            throw new \RuntimeException('La facture d’origine e‑MECeF est introuvable.');
        }

        $snapshot = is_array($creditNote->source_snapshot) ? $creditNote->source_snapshot : [];
        /** @var list<array{key: string, quantity: float}> $selection */
        $selection = array_values(array_filter(
            Arr::get($snapshot, 'lines', []),
            static fn (mixed $line): bool => is_array($line),
        ));
        $selectionByKey = collect($selection)->keyBy('key');
        $items = [];

        foreach ($emecf->items->values() as $index => $item) {
            $lineKey = 'line-'.$index;
            if ($selection !== [] && ! $selectionByKey->has($lineKey)) {
                continue;
            }

            $requestedQuantity = $selectionByKey->has($lineKey)
                ? (float) Arr::get($selectionByKey->get($lineKey, []), 'quantity', 0)
                : abs((float) $item->quantity);
            if ($requestedQuantity <= 0) {
                continue;
            }

            $items[] = array_filter([
                'code' => $item->code,
                'name' => $item->name,
                'price' => (int) $item->price,
                'quantity' => -abs($requestedQuantity),
                'taxGroup' => $item->tax_group->value,
                'taxSpecific' => $item->tax_specific,
                'originalPrice' => $item->original_price,
                'priceModification' => $item->price_modification,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        $operatorName = $creditNote->requestedBy !== null
            ? $creditNote->requestedBy->name
            : $emecf->operator_name;

        return [
            'ifu' => $emecf->ifu,
            'aib' => $emecf->aib,
            'type' => $emecf->type->value === 'EV' ? 'EA' : 'FA',
            'reference' => $this->originalReference($emecf->code_mec_ef_dgi),
            'operator' => array_filter([
                'id' => $creditNote->requestedBy?->uuid,
                'name' => $operatorName,
            ]),
            'client' => array_filter([
                'ifu' => $emecf->client_ifu,
                'name' => $emecf->client_name,
                'contact' => $emecf->client_contact,
                'address' => $emecf->client_address,
            ]),
            'items' => $items,
        ];
    }

    private function originalReference(?string $reference): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]/', '', (string) $reference) ?? '';

        if (strlen($normalized) !== 24) {
            throw new \RuntimeException('La facture d’origine ne possède pas de référence MECeF/DGI exploitable.');
        }

        return $normalized;
    }
}
