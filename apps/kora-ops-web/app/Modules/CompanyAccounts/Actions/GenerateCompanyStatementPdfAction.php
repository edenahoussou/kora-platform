<?php

namespace App\Modules\CompanyAccounts\Actions;

use App\Modules\CompanyAccounts\Models\CompanyBillingStatement;
use App\Modules\CompanyAccounts\Services\CompanyBillingStatementDocumentService;
use Spatie\Browsershot\Browsershot;

/**
 * Relevé de facturation mensuel A4 portrait en PDF — document brandé KORA
 * téléchargeable depuis le back-office (onglet Factures du compte).
 */
class GenerateCompanyStatementPdfAction
{
    public function __construct(
        private readonly CompanyBillingStatementDocumentService $documents,
    ) {}

    /** @return array{content: string, filename: string} */
    public function execute(CompanyBillingStatement $statement): array
    {
        $html = view('company-accounts.statement', $this->documents->payload($statement))->render();

        return [
            'content' => $this->renderPdf($html),
            'filename' => $this->filenameFor($statement),
        ];
    }

    protected function filenameFor(CompanyBillingStatement $statement): string
    {
        $reference = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $statement->reference) ?: 'releve';

        return "releve-{$reference}.pdf";
    }

    protected function renderPdf(string $html): string
    {
        $profile = config('pdf.profiles.statement', []);
        $configuredCommonArgs = config('pdf.common_args', []);
        $commonArgs = is_array($configuredCommonArgs)
            ? array_values(array_filter($configuredCommonArgs, 'is_string'))
            : [];
        $profileArgs = is_array($profile['args'] ?? null)
            ? array_values(array_filter($profile['args'], 'is_string'))
            : [];
        $chromiumArguments = array_values(array_unique(array_map(
            static fn (string $argument): string => ltrim($argument, '-'),
            array_merge($commonArgs, $profileArgs),
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
            ->margins(...($profile['margins'] ?? [0, 0, 0, 0]));

        if ($profile['landscape'] ?? false) {
            $browsershot->landscape();
        }

        $browsershot
            ->timeout(max(1, (int) ceil(((int) config('pdf.timeout', 60000)) / 1000)));

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

        return $browsershot->pdf();
    }
}
