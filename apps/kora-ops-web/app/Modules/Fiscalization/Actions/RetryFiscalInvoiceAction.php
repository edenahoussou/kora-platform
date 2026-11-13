<?php

namespace App\Modules\Fiscalization\Actions;

use App\Jobs\FiscalizeReservationInvoiceJob;
use App\Models\User;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Parcels\Jobs\FiscalizeParcelInvoiceJob;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RetryFiscalInvoiceAction
{
    public function execute(FiscalInvoice $invoice, User $actor): FiscalInvoice
    {
        $retry = DB::transaction(function () use ($invoice, $actor): FiscalInvoice {
            $locked = FiscalInvoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->getRawOriginal('status') !== FiscalInvoiceStatus::Failed->value) {
                throw new ConflictHttpException('Cette facture n’est plus disponible pour une relance.');
            }

            $locked->update(['status' => FiscalInvoiceStatus::Pending, 'last_error' => null]);
            activity('fiscalization')
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties(['invoice_uuid' => $locked->uuid])
                ->log('fiscal_invoice_retry_requested');

            return $locked;
        }, attempts: 3);

        if ($retry->parcel_charge_id !== null) {
            FiscalizeParcelInvoiceJob::dispatch($retry->id);
        } else {
            FiscalizeReservationInvoiceJob::dispatch($retry->reservation_id);
        }

        return $retry;
    }
}
