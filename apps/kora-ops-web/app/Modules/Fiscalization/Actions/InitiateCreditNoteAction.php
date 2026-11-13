<?php

namespace App\Modules\Fiscalization\Actions;

use App\Jobs\IssueFiscalCreditNoteJob;
use App\Models\User;
use App\Modules\Fiscalization\Data\FiscalInvoiceDocumentKind;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use Codianselme\LaraSygmef\Models\EmecfInvoiceItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitiateCreditNoteAction
{
    /** @param array{mode: string, reason: string, idempotency_key: string, lines?: list<array{key: string, quantity: float}>} $data */
    public function execute(FiscalInvoice $original, array $data, User $actor): FiscalInvoice
    {
        $creditNote = DB::transaction(function () use ($original, $data, $actor): FiscalInvoice {
            $locked = FiscalInvoice::query()
                ->with(['emecfInvoice.items', 'creditNotes.emecfInvoice.items'])
                ->whereKey($original->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->document_kind === FiscalInvoiceDocumentKind::CreditNote || $locked->status !== FiscalInvoiceStatus::Certified) {
                throw new \DomainException('Seule une facture de vente certifiée peut recevoir un avoir.');
            }

            if ($locked->emecfInvoice === null || ! in_array($locked->emecfInvoice->type->value, ['FV', 'EV'], true)) {
                throw new \DomainException('La facture fiscale originale ne peut pas recevoir un avoir.');
            }

            $existing = FiscalInvoice::query()
                ->where('credit_note_idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $lines = $this->validatedLines($locked, $data);
            if ($lines === []) {
                throw new \DomainException('Toutes les lignes de cette facture ont déjà été créditées.');
            }

            $uuid = (string) Str::uuid();
            $creditNote = FiscalInvoice::query()->create([
                'uuid' => $uuid,
                'reservation_id' => $locked->reservation_id,
                'parcel_charge_id' => $locked->parcel_charge_id,
                'payment_id' => $locked->payment_id,
                'kora_reference' => 'KORA-AVOIR-'.$uuid,
                'status' => FiscalInvoiceStatus::Pending,
                'document_kind' => FiscalInvoiceDocumentKind::CreditNote,
                'original_fiscal_invoice_id' => $locked->getKey(),
                'requested_by_user_id' => $actor->getKey(),
                'credit_note_reason' => $data['reason'],
                'credit_note_idempotency_key' => $data['idempotency_key'],
                'source_snapshot' => [
                    'mode' => $data['mode'],
                    'lines' => $lines,
                    'reason' => $data['reason'],
                ],
                'requested_at' => now(),
                'total_without_tax' => 0,
                'total_tax' => 0,
                'total_with_tax' => 0,
                'tax_snapshot' => [],
            ]);

            IssueFiscalCreditNoteJob::dispatch($creditNote->getKey())->afterCommit();

            return $creditNote;
        });

        return $creditNote;
    }

    /**
     * @param  array{mode: string, reason: string, idempotency_key: string, lines?: list<array{key: string, quantity: float}>}  $data
     * @return list<array{key: string, quantity: float}>
     */
    private function validatedLines(FiscalInvoice $original, array $data): array
    {
        if ($original->emecfInvoice === null) {
            throw new \DomainException('La facture fiscale originale est introuvable.');
        }

        $items = $original->emecfInvoice->items->values();
        /** @var array<string, float> $creditedByLine */
        $creditedByLine = [];
        foreach ($original->creditNotes as $creditNote) {
            if ($creditNote->status === FiscalInvoiceStatus::Failed) {
                continue;
            }

            $creditItems = $creditNote->emecfInvoice?->items;
            if ($creditItems !== null && $creditItems->isNotEmpty()) {
                foreach ($creditItems as $item) {
                    $key = $this->lineKey($item);
                    $creditedByLine[$key] = ($creditedByLine[$key] ?? 0) + abs((float) $item->quantity);
                }

                continue;
            }

            $snapshotLines = is_array($creditNote->source_snapshot)
                ? Arr::get($creditNote->source_snapshot, 'lines', [])
                : [];
            if (! is_array($snapshotLines)) {
                continue;
            }

            foreach ($snapshotLines as $line) {
                if (! is_array($line) || ! isset($line['key'], $line['quantity'])) {
                    continue;
                }

                $index = (int) str_replace('line-', '', (string) $line['key']);
                $originalItem = $items->get($index);
                if (! $originalItem instanceof EmecfInvoiceItem) {
                    continue;
                }

                $key = $this->lineKey($originalItem);
                $creditedByLine[$key] = ($creditedByLine[$key] ?? 0) + abs((float) $line['quantity']);
            }
        }

        $lines = [];
        if ($data['mode'] === 'full') {
            foreach ($items as $index => $item) {
                $quantity = max(0.0, abs((float) $item->quantity) - ($creditedByLine[$this->lineKey($item)] ?? 0));
                if ($quantity > 0) {
                    $lines[] = ['key' => 'line-'.$index, 'quantity' => $quantity];
                }
            }

            return $lines;
        }

        /** @var array<string, array{key: string, quantity: float}> $requested */
        $requested = [];
        $rawLines = Arr::get($data, 'lines', []);
        if (is_array($rawLines)) {
            foreach ($rawLines as $line) {
                if (is_array($line) && isset($line['key'], $line['quantity'])) {
                    $requested[(string) $line['key']] = [
                        'key' => (string) $line['key'],
                        'quantity' => (float) $line['quantity'],
                    ];
                }
            }
        }
        foreach ($items as $index => $item) {
            $key = 'line-'.$index;
            $quantity = (float) ($requested[$key]['quantity'] ?? 0);
            $alreadyCredited = $creditedByLine[$this->lineKey($item)] ?? 0;
            $remaining = max(0.0, abs((float) $item->quantity) - $alreadyCredited);

            if ($quantity > $remaining) {
                throw new \DomainException('La quantité demandée dépasse le reste disponible pour une ligne.');
            }

            if ($quantity > 0) {
                $lines[] = ['key' => $key, 'quantity' => $quantity];
            }
        }

        return $lines;
    }

    private function lineKey(EmecfInvoiceItem $item): string
    {
        return $item->code ?? $item->name.'|'.$item->price;
    }
}
