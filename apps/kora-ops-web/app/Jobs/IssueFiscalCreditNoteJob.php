<?php

namespace App\Jobs;

use App\Models\User;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Services\CreditNotePayloadBuilder;
use App\Modules\Fiscalization\Services\EmecfInvoiceSnapshotService;
use App\Modules\Fiscalization\Services\FiscalSyncAttemptRecorder;
use App\Notifications\Fiscalization\CreditNoteStatusNotification;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Codianselme\LaraSygmef\Services\EmecfService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class IssueFiscalCreditNoteJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $creditNoteId) {}

    public function handle(
        CreditNotePayloadBuilder $payloadBuilder,
        FiscalSyncAttemptRecorder $attemptRecorder,
        EmecfInvoiceSnapshotService $snapshotService,
    ): void {
        $invoice = FiscalInvoice::query()->with(['requestedBy', 'originalFiscalInvoice.emecfInvoice.items'])->findOrFail($this->creditNoteId);

        if ($invoice->status === FiscalInvoiceStatus::Certified) {
            return;
        }

        $this->notify($invoice, 'processing');
        $invoice->update(['status' => FiscalInvoiceStatus::Pending, 'last_error' => null]);
        if (! $this->isEmecfConfigured()) {
            throw new \RuntimeException('e‑MECeF non configuré : EMECF_TOKEN ou SGMEF_DEFAULT_IFU manquant.');
        }

        $emecf = app(EmecfService::class);
        $payload = $payloadBuilder->build($invoice);
        $snapshot = is_array($invoice->source_snapshot) ? $invoice->source_snapshot : [];
        $invoice->update(['source_snapshot' => array_merge($snapshot, ['request' => $payload])]);
        $submitAttempt = ((int) $invoice->syncAttempts()->max('attempt_number')) + 1;
        $attempt = $attemptRecorder->start($invoice, 'credit_note_submit', $submitAttempt, $payload);
        $activeAttempt = $attempt;

        try {
            $submission = $emecf->submitInvoice($payload);
            if (($submission['success'] ?? false) !== true || empty($submission['data']['uid'])) {
                throw new \RuntimeException((string) ($submission['error'] ?? 'Soumission de l’avoir e‑MECeF échouée.'));
            }

            $attemptRecorder->succeed($attempt, $submission, (string) ($submission['status'] ?? 'submitted'));
            $uid = (string) $submission['data']['uid'];
            $invoice->update([
                'mecef_uid' => $uid,
                'emecf_invoice_id' => $submission['invoice_id'] ?? EmecfInvoice::query()->where('uid', $uid)->value('id'),
            ]);
            $snapshotService->synchronize($invoice->fresh('emecfInvoice'), $payload, $submission);

            $finalizeAttempt = ((int) $invoice->syncAttempts()->max('attempt_number')) + 1;
            $activeAttempt = $attemptRecorder->start($invoice, 'credit_note_finalize', $finalizeAttempt, ['uid' => $uid]);
            $finalization = $emecf->finalizeInvoice($uid, 'confirm');
            if (($finalization['success'] ?? false) !== true) {
                throw new \RuntimeException((string) ($finalization['error'] ?? 'Certification de l’avoir e‑MECeF échouée.'));
            }

            $attemptRecorder->succeed($activeAttempt, $finalization, (string) ($finalization['status'] ?? 'confirmed'));
            $invoice->update([
                'status' => FiscalInvoiceStatus::Certified,
                'fiscalized_at' => now(),
                'source_snapshot' => array_merge(
                    is_array($invoice->fresh()->source_snapshot) ? $invoice->fresh()->source_snapshot : [],
                    ['finalization' => $finalization],
                ),
            ]);
            $this->notify($invoice->fresh(), 'certified');
        } catch (Throwable $exception) {
            if ($activeAttempt->fresh()?->completed_at === null) {
                $attemptRecorder->fail($activeAttempt, $exception, ['message' => $exception->getMessage()]);
            }
            $invoice->update(['status' => FiscalInvoiceStatus::Failed, 'last_error' => $exception->getMessage()]);
            Log::warning('Fiscal credit note failed.', ['fiscal_invoice_id' => $invoice->getKey(), 'exception' => $exception]);
            throw $exception;
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->creditNoteId;
    }

    public function failed(?Throwable $exception): void
    {
        $invoice = FiscalInvoice::query()->with('requestedBy')->find($this->creditNoteId);
        if ($invoice === null) {
            return;
        }

        $invoice->update([
            'status' => FiscalInvoiceStatus::Failed,
            'last_error' => $exception?->getMessage() ?? 'L’émission de la facture d’avoir a échoué.',
        ]);
        $this->notify($invoice, 'failed');
    }

    private function notify(FiscalInvoice $invoice, string $event): void
    {
        $recipients = collect([$invoice->requestedBy])
            ->merge(User::query()->permission('fiscalization.credit')->get())
            ->filter()
            ->unique(fn ($user) => $user->getKey());

        Notification::send($recipients, new CreditNoteStatusNotification($invoice, $event));
    }

    private function isEmecfConfigured(): bool
    {
        $token = (string) config('emecf.token');
        $ifu = (string) config('emecf.default_ifu');

        return $token !== '' && $ifu !== '' && $ifu !== '0000000000000';
    }
}
