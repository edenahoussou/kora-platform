<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Fiscalization\Models\FiscalInvoice;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Codianselme\LaraSygmef\Models\EmecfInvoiceItem;
use Illuminate\Support\Arr;

class EmecfInvoiceSnapshotService
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $submission
     * @param  array<string, mixed>  $finalization
     */
    public function synchronize(FiscalInvoice $invoice, array $payload, array $submission = [], array $finalization = []): void
    {
        $uid = (string) Arr::get($submission, 'data.uid', '');
        $emecf = $invoice->emecfInvoice;

        if ($emecf === null && $uid !== '') {
            $emecf = EmecfInvoice::query()->where('uid', $uid)->first();
        }

        if ($emecf === null) {
            return;
        }

        $this->synchronizeItems($emecf, $this->listOfArrays($payload['items'] ?? []));
        $this->synchronizePayments($emecf, $this->listOfArrays($payload['payment'] ?? []));

        $snapshot = is_array($invoice->source_snapshot) ? $invoice->source_snapshot : [];
        $invoice->update([
            'emecf_invoice_id' => $emecf->getKey(),
            'mecef_uid' => $emecf->uid,
            'source_snapshot' => array_merge($snapshot, [
                'request' => $payload,
                'submission' => $submission,
                'finalization' => $finalization,
            ]),
        ]);
    }

    /** @param list<array<string, mixed>> $items */
    private function synchronizeItems(EmecfInvoice $invoice, array $items): void
    {
        $existing = $invoice->items()->oldest('id')->get();

        foreach ($items as $index => $item) {
            /** @var EmecfInvoiceItem|null $line */
            $line = $existing->get($index);
            $attributes = [
                'code' => $item['code'] ?? null,
                'name' => (string) ($item['name'] ?? 'Article fiscal'),
                'price' => (int) ($item['price'] ?? 0),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'tax_group' => (string) ($item['taxGroup'] ?? 'A'),
                'tax_specific' => isset($item['taxSpecific']) ? (int) $item['taxSpecific'] : null,
                'original_price' => isset($item['originalPrice']) ? (int) $item['originalPrice'] : null,
                'price_modification' => $item['priceModification'] ?? null,
            ];

            if ($line === null) {
                $invoice->items()->create($attributes);
            } else {
                $line->update($attributes);
            }
        }
    }

    /** @param list<array<string, mixed>> $payments */
    private function synchronizePayments(EmecfInvoice $invoice, array $payments): void
    {
        if ($payments === []) {
            return;
        }

        $invoice->payments()->delete();

        foreach ($payments as $payment) {
            $invoice->payments()->create([
                'name' => (string) ($payment['name'] ?? 'AUTRE'),
                'amount' => (int) ($payment['amount'] ?? 0),
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function listOfArrays(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
    }
}
