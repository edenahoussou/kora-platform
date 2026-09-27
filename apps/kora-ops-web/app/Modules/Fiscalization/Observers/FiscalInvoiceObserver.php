<?php

namespace App\Modules\Fiscalization\Observers;

use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Notifications\Services\OpsNotifier;

class FiscalInvoiceObserver
{
    public function __construct(private OpsNotifier $notifier) {}

    public function updated(FiscalInvoice $invoice): void
    {
        if ($invoice->wasChanged('status') && $invoice->status === FiscalInvoiceStatus::Failed) {
            $this->notifier->fiscalizationFailed($invoice);
        }
    }
}
