<?php

namespace App\Modules\Parcels\Actions;

use Spatie\Browsershot\Browsershot;

/**
 * Rend le bordereau d’envoi Colis en PDF A4 paysage via Chromium.
 *
 * Le HTML vient de resources/views/parcels/label.blade.php : la même vue sert
 * à l’aperçu écran et au PDF, pour éviter deux mises en page divergentes.
 */
class GenerateParcelLabelPdfAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{content: string, filename: string}
     */
    public function render(array $payload, string $reference): array
    {
        $html = view('parcels.label', [...$payload, 'pdf' => true])->render();

        return [
            'content' => $this->renderPdf($html),
            'filename' => 'bordereau-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $reference).'.pdf',
        ];
    }

    protected function renderPdf(string $html): string
    {
        $profile = config('pdf.profiles.parcel_label_a4', []);
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
            ->windowSize(...($profile['window_size'] ?? [1123, 794]))
            ->deviceScaleFactor($profile['device_scale_factor'] ?? 2)
            ->paperSize(...($profile['paper_size'] ?? [210, 297]))
            ->margins(...($profile['margins'] ?? [0, 0, 0, 0]))
            ->timeout(max(1, (int) ceil(((int) config('pdf.timeout', 60000)) / 1000)));

        if ($profile['landscape'] ?? false) {
            $browsershot->landscape();
        }

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

        if ($profile['wait_until_network_idle'] ?? false) {
            $browsershot->waitUntilNetworkIdle();
        }

        return $browsershot->pdf();
    }
}
