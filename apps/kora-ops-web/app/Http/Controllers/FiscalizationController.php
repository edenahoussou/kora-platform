<?php

namespace App\Http\Controllers;

use App\Jobs\FiscalizeReservationInvoiceJob;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FiscalizationController extends Controller
{
    public function retry(FiscalInvoice $fiscalInvoice): RedirectResponse
    {
        Gate::authorize('retry', $fiscalInvoice);
        abort_unless($fiscalInvoice->getRawOriginal('status') === FiscalInvoiceStatus::Failed->value, 422, 'Seules les factures en échec peuvent être relancées.');
        $fiscalInvoice->update(['status' => FiscalInvoiceStatus::Pending]);
        FiscalizeReservationInvoiceJob::dispatch($fiscalInvoice->reservation_id)->onConnection('database');

        return back();
    }
}
