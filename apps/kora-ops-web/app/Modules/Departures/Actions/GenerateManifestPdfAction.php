<?php

namespace App\Modules\Departures\Actions;

use App\Modules\Departures\Exceptions\BorderManifestIncompleteException;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\ManifestDocumentService;
use Spatie\Browsershot\Browsershot;

/**
 * Manifeste passagers A4 paysage en PDF — document partagé entre le web
 * (bouton « Télécharger » de la prévisualisation) et, à terme, OPS Mobile
 * en remplacement du manifeste frontière actuel.
 *
 * La composition du document (société, départ, compteurs, passagers avec
 * données sensibles masquées) vient entièrement de ManifestDocumentService :
 * web, PDF et mobile voient exactement le même contenu.
 */
class GenerateManifestPdfAction
{
    public function __construct(
        private readonly ManifestDocumentService $documents,
    ) {}

    /**
     * @return array{content: string, filename: string}
     *
     * @throws BorderManifestIncompleteException départ sans voyageur, ou
     *                                           ligne internationale avec des identités obligatoires manquantes
     *                                           — rendu en 422 `manifest_incomplete` (contrat OPS Mobile).
     */
    public function execute(Departure $departure): array
    {
        $payload = $this->documents->payload($departure);

        if ($payload['travellingCount'] === 0) {
            throw BorderManifestIncompleteException::noPassengers();
        }

        if ($payload['international'] && $payload['incompletePassengers'] !== []) {
            throw new BorderManifestIncompleteException($payload['incompletePassengers']);
        }

        return $this->renderPayload($payload, $this->filenameFor($departure));
    }

    /**
     * Rend un payload de manifeste (réel ou fictif pour la prévisualisation)
     * en PDF binaire.
     *
     * @param  array<string, mixed>  $payload
     * @return array{content: string, filename: string}
     */
    public function renderPayload(array $payload, string $filename): array
    {
        $html = view('manifests.manifest', $payload)->render();

        return [
            'content' => $this->renderPdf($html),
            'filename' => $filename,
        ];
    }

    protected function filenameFor(Departure $departure): string
    {
        $lineCode = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $departure->line->code) ?: 'ligne';
        $date = $departure->departure_date?->format('Y-m-d') ?? 'depart';

        return "manifeste-{$lineCode}-{$date}.pdf";
    }

    protected function renderPdf(string $html): string
    {
        $profile = config('pdf.profiles.manifest_a4', []);
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
