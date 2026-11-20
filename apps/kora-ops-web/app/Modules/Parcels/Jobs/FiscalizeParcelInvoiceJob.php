<?php

namespace App\Modules\Parcels\Jobs;

use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Services\EmecfInvoiceSnapshotService;
use App\Modules\Fiscalization\Services\FiscalSyncAttemptRecorder;
use App\Modules\Parcels\Models\ParcelCharge;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Codianselme\LaraSygmef\Enums\InvoiceStatus;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Codianselme\LaraSygmef\Services\EmecfService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class FiscalizeParcelInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $invoiceId) {}

    public function uniqueId(): string
    {
        return (string) $this->invoiceId;
    }

    public function handle(IntegrationsSettings $settings, FiscalSyncAttemptRecorder $attempts, EmecfInvoiceSnapshotService $snapshots): void
    {
        $invoice = FiscalInvoice::query()->with(['payment', 'emecfInvoice'])->findOrFail($this->invoiceId);
        if ($invoice->status === FiscalInvoiceStatus::Certified) {
            return;
        }
        $charge = ParcelCharge::query()->with('parcel')->findOrFail($invoice->parcel_charge_id);
        $parcel = $charge->parcel;
        $payload = [
            'ifu' => $settings->emecfDefaultIfu,
            'type' => 'FV',
            'operator' => ['name' => 'KORA COLIS'],
            'client' => ['name' => $parcel->sender_name, 'contact' => $parcel->sender_phone],
            'items' => [[
                'code' => $invoice->kora_reference,
                'name' => $charge->return_leg ? 'Retour KORA COLIS' : 'Transport KORA COLIS',
                'price' => (int) $charge->total,
                'quantity' => 1,
                'taxGroup' => $charge->fiscal_group,
                'taxSpecific' => 0,
            ]],
            'payment' => [[
                'name' => match ($invoice->payment?->method?->value) {
                    'cash' => 'ESPECES',
                    'mobile_money' => 'MOBILEMONEY',
                    default => 'AUTRE',
                },
                'amount' => (int) $charge->total,
            ]],
        ];

        config([
            'emecf.test_mode' => $settings->emecfTestMode,
            'emecf.token' => $settings->emecfToken,
            'emecf.default_ifu' => $settings->emecfDefaultIfu,
            'emecf.http.timeout' => $settings->emecfHttpTimeout,
            'emecf.http.connect_timeout' => $settings->emecfConnectTimeout,
            'emecf.http.retry' => $settings->emecfRetry,
            'emecf.http.retry_delay' => $settings->emecfRetryDelay,
            'emecf.timeouts.invoice_submission' => $settings->emecfInvoiceSubmissionTimeout,
            'emecf.timeouts.finalization' => $settings->emecfFinalizationTimeout,
        ]);
        $invoice->update(['status' => FiscalInvoiceStatus::Pending]);
        $attempt = null;

        try {
            if (blank($settings->emecfToken) || blank($settings->emecfDefaultIfu) || $settings->emecfDefaultIfu === '0000000000000') {
                throw new RuntimeException('Parcel e-MECeF service is unavailable.');
            }
            $emecf = app(EmecfService::class);
            $submission = [];
            $uid = $invoice->mecef_uid;
            if ($uid === null) {
                $attempt = $attempts->start($invoice, 'submit', $this->nextAttempt($invoice, 'submit'), $payload);
                $submission = $emecf->submitInvoice($payload);
                if (($submission['success'] ?? false) !== true || empty($submission['data']['uid'])) {
                    throw new RuntimeException('Parcel e-MECeF submission failed.');
                }
                $uid = (string) $submission['data']['uid'];
                $invoice->update(['mecef_uid' => $uid, 'emecf_invoice_id' => $submission['invoice_id'] ?? EmecfInvoice::query()->where('uid', $uid)->value('id')]);
                $attempts->succeed($attempt, $submission);
                $snapshots->synchronize($invoice->refresh(), $payload, $submission);
            }

            $attempt = $attempts->start($invoice, 'finalize', $this->nextAttempt($invoice, 'finalize'), ['uid' => $uid]);
            $result = $emecf->finalizeInvoice($uid, 'confirm');
            if (($result['success'] ?? false) !== true) {
                $result = $emecf->getInvoiceStatus($uid);
            }
            $code = $result['data']['codeMECeFDGI'] ?? null;
            $qr = $result['data']['qrCode'] ?? null;
            $externalInvoice = $invoice->refresh()->emecfInvoice;
            if (blank($code) || blank($qr) || $externalInvoice === null) {
                throw new RuntimeException('Parcel e-MECeF certification failed.');
            }
            $externalInvoice->update([
                'status' => InvoiceStatus::CONFIRMED, 'finalized_at' => $externalInvoice->finalized_at ?? now(),
                'code_mec_ef_dgi' => $code, 'qr_code' => $qr,
                'date_time' => $result['data']['dateTime'] ?? null,
                'counters' => $result['data']['counters'] ?? null,
                'nim' => $result['data']['nim'] ?? null,
            ]);
            $snapshots->synchronize($invoice->refresh(), $payload, $submission, $result);
            $attempts->succeed($attempt, $result);
            $invoice->update(['status' => FiscalInvoiceStatus::Certified, 'fiscalized_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            if ($attempt?->fresh()?->completed_at === null && $attempt !== null) {
                $attempts->fail($attempt, $exception);
            }
            $invoice->update(['status' => FiscalInvoiceStatus::Failed, 'last_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function nextAttempt(FiscalInvoice $invoice, string $operation): int
    {
        return 1 + (int) $invoice->syncAttempts()->where('operation', $operation)->max('attempt_number');
    }
}
