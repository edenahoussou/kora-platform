<?php

namespace App\Jobs;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Services\EmecfInvoiceSnapshotService;
use App\Modules\Fiscalization\Services\FiscalInvoicePayloadBuilder;
use App\Modules\Fiscalization\Services\FiscalSyncAttemptRecorder;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Codianselme\LaraSygmef\Services\EmecfService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FiscalizeReservationInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $reservationId) {}

    /**
     * Execute the job.
     */
    public function handle(
        FiscalInvoicePayloadBuilder $payloadBuilder,
        ?FiscalSyncAttemptRecorder $attemptRecorder = null,
        ?EmecfInvoiceSnapshotService $snapshotService = null,
    ): void {
        $this->applyEmecfSettings();
        $attemptRecorder ??= app(FiscalSyncAttemptRecorder::class);
        $snapshotService ??= app(EmecfInvoiceSnapshotService::class);
        $reservation = Reservation::query()->findOrFail($this->reservationId);
        $payment = $reservation->payments()->where('status', 'confirmed')->latest('id')->firstOrFail();
        $invoice = FiscalInvoice::query()->firstOrCreate(['reservation_id' => $reservation->id], [
            'payment_id' => $payment->id,
            'kora_reference' => 'KORA-'.$reservation->uuid,
            'status' => FiscalInvoiceStatus::Pending,
            'total_without_tax' => (int) $reservation->total_amount,
            'total_tax' => 0,
            'total_with_tax' => (int) $reservation->total_amount,
            'tax_snapshot' => ['transport' => ['tax_group' => 'A', 'rate' => 0]],
        ]);

        if ($invoice->getRawOriginal('status') === FiscalInvoiceStatus::Certified->value) {
            return;
        }
        if ($invoice->getRawOriginal('status') === FiscalInvoiceStatus::Failed->value) {
            $invoice->update(['status' => FiscalInvoiceStatus::Pending]);
        }

        if (! $this->isEmecfConfigured()) {
            $message = 'e-MECeF non configuré : EMECF_TOKEN ou SGMEF_DEFAULT_IFU manquant dans l’environnement.';
            $attempt = $attemptRecorder->start($invoice, 'submit', $this->nextAttemptNumber($invoice, 'submit'), []);
            $attemptRecorder->fail($attempt, new \RuntimeException($message));
            $invoice->update(['status' => FiscalInvoiceStatus::Failed, 'last_error' => $message]);
            Log::warning($message, ['reservation_id' => $this->reservationId]);

            return;
        }

        $payload = $payloadBuilder->build($reservation);
        $submitAttempt = $attemptRecorder->start($invoice, 'submit', $this->nextAttemptNumber($invoice, 'submit'), $payload);
        $activeAttempt = $submitAttempt;

        try {
            $emecf = app(EmecfService::class);
            $submission = $emecf->submitInvoice($payload);
            if (($submission['success'] ?? false) !== true || empty($submission['data']['uid'])) {
                $exception = new \RuntimeException((string) ($submission['error'] ?? 'Soumission e-MECeF échouée.'));
                $attemptRecorder->fail($submitAttempt, $exception, $submission);
                throw $exception;
            }
            $attemptRecorder->succeed($submitAttempt, $submission, isset($submission['status']) ? (string) $submission['status'] : null);
            $uid = (string) $submission['data']['uid'];
            $invoice->update(['mecef_uid' => $uid, 'emecf_invoice_id' => $submission['invoice_id'] ?? EmecfInvoice::query()->where('uid', $uid)->value('id')]);
            $snapshotService->synchronize($invoice->fresh('emecfInvoice'), $payload, $submission);
            $finalizeAttempt = $attemptRecorder->start($invoice, 'finalize', $this->nextAttemptNumber($invoice, 'finalize'), ['uid' => $uid]);
            $activeAttempt = $finalizeAttempt;
            $finalization = $emecf->finalizeInvoice($uid, 'confirm');
            if (($finalization['success'] ?? false) !== true) {
                $exception = new \RuntimeException((string) ($finalization['error'] ?? 'Certification e-MECeF échouée.'));
                $attemptRecorder->fail($finalizeAttempt, $exception, $finalization);
                throw $exception;
            }
            $attemptRecorder->succeed($finalizeAttempt, $finalization, isset($finalization['status']) ? (string) $finalization['status'] : null);
            $snapshotService->synchronize($invoice->fresh('emecfInvoice'), $payload, $submission, $finalization);
            $invoice->update(['status' => FiscalInvoiceStatus::Certified, 'fiscalized_at' => now()]);
        } catch (Throwable $exception) {
            if ($activeAttempt->fresh()?->completed_at === null) {
                $attemptRecorder->fail($activeAttempt, $exception, ['message' => $exception->getMessage()]);
            }
            $invoice->update(['status' => FiscalInvoiceStatus::Failed, 'last_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->reservationId;
    }

    public function failed(?Throwable $exception): void
    {
        FiscalInvoice::query()->where('reservation_id', $this->reservationId)->update([
            'status' => FiscalInvoiceStatus::Failed,
            'last_error' => $exception?->getMessage() ?? 'La fiscalisation a échoué.',
        ]);
    }

    private function nextAttemptNumber(FiscalInvoice $invoice, string $operation): int
    {
        return ((int) $invoice->syncAttempts()->where('operation', $operation)->max('attempt_number')) + 1;
    }

    private function isEmecfConfigured(): bool
    {
        $settings = app(IntegrationsSettings::class);
        $token = (string) ($settings->emecfToken ?? '');
        $ifu = $settings->emecfDefaultIfu;

        return $token !== '' && $ifu !== '' && $ifu !== '0000000000000';
    }

    private function applyEmecfSettings(): void
    {
        $settings = app(IntegrationsSettings::class);

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
            'emecf.timeouts.status_check' => $settings->emecfStatusCheckTimeout,
            'emecf.timeouts.info_request' => $settings->emecfInfoRequestTimeout,
        ]);
    }
}
