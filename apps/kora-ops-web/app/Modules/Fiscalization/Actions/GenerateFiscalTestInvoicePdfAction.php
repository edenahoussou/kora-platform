<?php

namespace App\Modules\Fiscalization\Actions;

use App\Modules\Fiscalization\Models\FiscalTestInvoiceRun;
use App\Modules\Fiscalization\Services\FiscalTestInvoicePresenter;

class GenerateFiscalTestInvoicePdfAction
{
    public function __construct(
        private readonly FiscalTestInvoicePresenter $presenter,
        private readonly GenerateFiscalInvoicePdfAction $generateFiscalInvoicePdf,
    ) {}

    /** @return array{content: string, filename: string} */
    public function execute(FiscalTestInvoiceRun $run): array
    {
        $run->loadMissing(['emecfInvoice.items', 'emecfInvoice.payments', 'requestedBy']);

        return $this->generateFiscalInvoicePdf->executeDetails($this->presenter->detail($run));
    }

    public function renderRun(FiscalTestInvoiceRun $run, ?string $pdfUrl = null, ?string $backUrl = null): string
    {
        $run->loadMissing(['emecfInvoice.items', 'emecfInvoice.payments', 'requestedBy']);

        return $this->generateFiscalInvoicePdf->renderDetails(
            $this->presenter->detail($run),
            isPdf: false,
            pdfUrl: $pdfUrl,
            backUrl: $backUrl,
        );
    }
}
