<?php

namespace App\Jobs;

use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalTestInvoiceRun;
use App\Modules\Fiscalization\Services\FiscalTestInvoiceGateway;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Codianselme\LaraSygmef\Enums\InvoiceStatus;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Codianselme\LaraSygmef\Models\EmecfInvoiceItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunFiscalTestInvoice implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $runId) {}

    public function handle(FiscalTestInvoiceGateway $gateway, IntegrationsSettings $settings): void
    {
        $run = FiscalTestInvoiceRun::query()
            ->with(['sourceRun.emecfInvoice.items', 'requestedBy'])
            ->findOrFail($this->runId);

        if (! $settings->emecfTestMode) {
            $run->update([
                'status' => FiscalInvoiceStatus::Failed->value,
                'last_error' => 'Le mode e‑MECeF TEST n’est pas activé.',
            ]);

            return;
        }

        try {
            $payload = $this->payloadForRun($run);
            $run->update([
                'status' => FiscalInvoiceStatus::Pending->value,
                'last_error' => null,
                'request_snapshot' => $payload,
            ]);
            $submission = $gateway->submit($payload);
            $uid = (string) ($submission['uid'] ?? '');
            if ($uid === '') {
                throw new \RuntimeException('e‑MECeF n’a pas retourné d’UID pour la facture.');
            }

            $emecfInvoice = $this->storeSubmittedInvoice($payload, $submission);
            $run->update([
                'emecf_invoice_id' => $emecfInvoice->getKey(),
            ]);

            $finalization = $gateway->finalize($uid);
            $emecfInvoice->update([
                'status' => InvoiceStatus::CONFIRMED,
                'code_mec_ef_dgi' => $finalization['codeMECeFDGI'] ?? null,
                'qr_code' => $finalization['qrCode'] ?? null,
                'date_time' => $finalization['dateTime'] ?? null,
                'counters' => $finalization['counters'] ?? null,
                'nim' => $finalization['nim'] ?? null,
                'finalized_at' => now(),
            ]);
            $run->update(['status' => FiscalInvoiceStatus::Certified->value]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => FiscalInvoiceStatus::Failed->value,
                'last_error' => $exception->getMessage(),
            ]);
            Log::warning('e-MECeF fiscal test invoice failed.', [
                'fiscal_test_invoice_run_uuid' => $run->uuid,
                'case_number' => $run->case_number,
                'exception' => $exception,
            ]);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    /** @return array<string, mixed> */
    private function payloadForRun(FiscalTestInvoiceRun $run): array
    {
        $requestSnapshot = $run->getAttribute('request_snapshot');
        $payload = is_array($requestSnapshot) ? $requestSnapshot : [];
        if ($run->case_number !== 6) {
            return $payload;
        }

        $sourceRun = $run->sourceRun()->first();
        $original = $sourceRun?->emecfInvoice;
        if ($original === null || $sourceRun->status !== FiscalInvoiceStatus::Certified->value) {
            throw new \RuntimeException('Une facture certifiée du test 5 est nécessaire pour émettre le test 6.');
        }

        $reference = preg_replace('/[^A-Za-z0-9]/', '', (string) $original->code_mec_ef_dgi) ?? '';
        if (strlen($reference) !== 24) {
            throw new \RuntimeException('La facture du test 5 ne possède pas de référence DGI valide pour l’avoir.');
        }

        return [
            'ifu' => $original->ifu,
            'aib' => $original->aib,
            'type' => $original->type->value === 'EV' ? 'EA' : 'FA',
            'reference' => $reference,
            'operator' => [
                'id' => $run->requestedBy?->uuid,
                'name' => $run->requestedBy->name ?? $original->operator_name,
            ],
            'client' => array_filter([
                'ifu' => $original->client_ifu,
                'name' => $original->client_name,
                'contact' => $original->client_contact,
                'address' => $original->client_address,
            ], static fn (mixed $value): bool => filled($value)),
            'items' => $original->items->map(static fn (EmecfInvoiceItem $item): array => array_filter([
                'code' => $item->code,
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => -abs((float) $item->quantity),
                'taxGroup' => $item->tax_group->value,
                'taxSpecific' => $item->tax_specific,
            ], static fn (mixed $value): bool => $value !== null))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     */
    private function storeSubmittedInvoice(array $payload, array $response): EmecfInvoice
    {
        $invoice = EmecfInvoice::query()->create([
            'uid' => (string) $response['uid'],
            'ifu' => (string) $payload['ifu'],
            'aib' => $payload['aib'] ?? null,
            'type' => (string) $payload['type'],
            'reference' => $payload['reference'] ?? null,
            'operator_id' => $payload['operator']['id'] ?? null,
            'operator_name' => (string) $payload['operator']['name'],
            'client_ifu' => $payload['client']['ifu'] ?? null,
            'client_name' => $payload['client']['name'] ?? null,
            'client_contact' => $payload['client']['contact'] ?? null,
            'client_address' => $payload['client']['address'] ?? null,
            'ta' => $response['ta'] ?? 0,
            'tb' => $response['tb'] ?? 0,
            'tc' => $response['tc'] ?? 0,
            'td' => $response['td'] ?? 0,
            'taa' => $response['taa'] ?? 0,
            'tab' => $response['tab'] ?? 0,
            'tac' => $response['tac'] ?? 0,
            'tad' => $response['tad'] ?? 0,
            'tae' => $response['tae'] ?? 0,
            'taf' => $response['taf'] ?? 0,
            'hab' => $response['hab'] ?? 0,
            'had' => $response['had'] ?? 0,
            'vab' => $response['vab'] ?? 0,
            'vad' => $response['vad'] ?? 0,
            'aib_amount' => $response['aib'] ?? 0,
            'ts' => $response['ts'] ?? 0,
            'total' => $response['total'] ?? 0,
            'status' => InvoiceStatus::PENDING,
            'submitted_at' => now(),
        ]);

        foreach ($payload['items'] as $item) {
            $invoice->items()->create([
                'code' => $item['code'] ?? null,
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'tax_group' => $item['taxGroup'],
                'tax_specific' => $item['taxSpecific'] ?? null,
            ]);
        }

        return $invoice;
    }
}
