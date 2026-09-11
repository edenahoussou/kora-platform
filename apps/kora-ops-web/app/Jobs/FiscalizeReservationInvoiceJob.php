<?php

namespace App\Jobs;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Services\FiscalInvoicePayloadBuilder;
use Codianselme\LaraSygmef\Models\EmecfInvoice;
use Codianselme\LaraSygmef\Services\EmecfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FiscalizeReservationInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $reservationId) {}

    /**
     * Execute the job.
     */
    public function handle(FiscalInvoicePayloadBuilder $payloadBuilder): void
    {
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
            $invoice->update(['status' => FiscalInvoiceStatus::Failed, 'last_error' => $message]);
            Log::warning($message, ['reservation_id' => $this->reservationId]);

            return;
        }

        try {
            $emecf = app(EmecfService::class);
            $submission = $emecf->submitInvoice($payloadBuilder->build($reservation));
            if (($submission['success'] ?? false) !== true || empty($submission['data']['uid'])) {
                throw new \RuntimeException((string) ($submission['error'] ?? 'Soumission e-MECeF échouée.'));
            }
            $uid = (string) $submission['data']['uid'];
            $invoice->update(['mecef_uid' => $uid, 'emecf_invoice_id' => $submission['invoice_id'] ?? EmecfInvoice::query()->where('uid', $uid)->value('id')]);
            $finalization = $emecf->finalizeInvoice($uid, 'confirm');
            if (($finalization['success'] ?? false) !== true) {
                throw new \RuntimeException((string) ($finalization['error'] ?? 'Certification e-MECeF échouée.'));
            }
            $invoice->update(['status' => FiscalInvoiceStatus::Certified, 'fiscalized_at' => now()]);
        } catch (Throwable $exception) {
            $invoice->update(['status' => FiscalInvoiceStatus::Failed, 'last_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function isEmecfConfigured(): bool
    {
        $token = (string) config('emecf.token');
        $ifu = (string) config('emecf.default_ifu');

        return $token !== '' && $ifu !== '' && $ifu !== '0000000000000';
    }
}
