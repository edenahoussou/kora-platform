<?php

namespace App\Modules\Fiscalization\Actions;

use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Services\FiscalInvoicePresenter;
use App\Modules\Fiscalization\Support\EmecfTaxVentilation;
use App\Modules\Ticketing\Actions\GenerateTicketQrCodeAction;
use Illuminate\Support\Number;
use Spatie\Browsershot\Browsershot;

class GenerateFiscalInvoicePdfAction
{
    public function __construct(
        private readonly FiscalInvoicePresenter $presenter,
        private readonly GenerateTicketQrCodeAction $generateQr,
    ) {}

    /** @return array{content: string, filename: string} */
    public function execute(FiscalInvoice $invoice): array
    {
        $invoice->loadMissing([
            'reservation',
            'emecfInvoice.items',
            'emecfInvoice.payments',
            'originalFiscalInvoice.emecfInvoice',
            'creditNotes.emecfInvoice.items',
            'syncAttempts',
        ]);

        return $this->executeDetails($this->presenter->detail($invoice, false));
    }

    /** @param array<string, mixed> $detail
     * @return array{content: string, filename: string}
     */
    public function executeDetails(array $detail): array
    {
        $fiscal = is_array($detail['fiscal'] ?? null) ? $detail['fiscal'] : [];
        $qrSvg = null;

        if (($fiscal['qrCode'] ?? null) !== null || ($fiscal['codeMecEfDgi'] ?? null) !== null) {
            $qrSvg = $this->generateQr->execute((string) ($fiscal['qrCode'] ?? $fiscal['codeMecEfDgi']));
        }

        $total = (int) ($fiscal['taxes']['total'] ?? $detail['amount'] ?? 0);

        $html = view('fiscalization.invoices.show', [
            'invoice' => $detail,
            'fiscal' => $fiscal,
            'qrSvg' => $qrSvg,
            'isPdf' => true,
            'ventilation' => EmecfTaxVentilation::fromTaxes(
                is_array($fiscal['taxes'] ?? null) ? $fiscal['taxes'] : [],
                $fiscal['aib'] ?? null,
            ),
            'amountInWords' => Number::spell($total, locale: 'fr'),
            'formatMoney' => static fn (mixed $amount): string => number_format((float) $amount, 0, ',', ' ').' XOF',
        ])->render();
        $profile = config('pdf.profiles.invoice', []);
        $commonArgs = config('pdf.common_args', []);
        $profileArgs = is_array($profile['args'] ?? null) ? $profile['args'] : [];
        $chromiumArguments = array_values(array_unique(array_map(
            static fn (string $argument): string => ltrim($argument, '-'),
            array_merge(is_array($commonArgs) ? $commonArgs : [], $profileArgs),
        )));

        $browsershot = Browsershot::html($html)
            ->noSandbox()
            ->addChromiumArguments(array_values(array_filter(
                $chromiumArguments,
                static fn (string $argument): bool => $argument !== 'no-sandbox',
            )))
            ->showBackground()
            ->emulateMedia($profile['emulate_media'] ?? 'print')
            ->windowSize(...($profile['window_size'] ?? [794, 1123]))
            ->deviceScaleFactor($profile['device_scale_factor'] ?? 2)
            ->paperSize(...($profile['paper_size'] ?? [210, 297]))
            ->margins(...($profile['margins'] ?? [10, 10, 10, 10]))
            ->timeout(config('pdf.timeout', 60000));

        $chromePath = config('pdf.chrome_path');
        if (is_string($chromePath) && $chromePath !== '') {
            $browsershot->setChromePath($chromePath);
        }

        $nodeBinary = config('pdf.node_binary');
        if (is_string($nodeBinary) && $nodeBinary !== '') {
            $browsershot->setNodeBinary($nodeBinary);
        }

        $chromeHome = config('pdf.home_dir');
        if (is_string($chromeHome) && $chromeHome !== '') {
            if (! is_dir($chromeHome) && ! mkdir($chromeHome, 0775, true) && ! is_dir($chromeHome)) {
                throw new \RuntimeException('Unable to prepare the Chromium home directory.');
            }
            $browsershot->setNodeEnv(['HOME' => $chromeHome]);
        }

        $content = $browsershot->pdf();
        $reference = (string) ($detail['reference'] ?? $detail['koraReference'] ?? 'facture-fiscale');

        return [
            'content' => $content,
            'filename' => 'facture-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $reference).'.pdf',
        ];
    }
}
