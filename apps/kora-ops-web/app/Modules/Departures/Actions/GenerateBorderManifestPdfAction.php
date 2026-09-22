<?php

namespace App\Modules\Departures\Actions;

use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Data\LineType;
use App\Modules\Departures\Data\ManifestSegmentStatus;
use App\Modules\Departures\Exceptions\BorderManifestIncompleteException;
use App\Modules\Departures\Models\Departure;
use Spatie\Browsershot\Browsershot;

use function Illuminate\Support\enum_value;

/**
 * Manifeste frontière d'un départ, en PDF A4 — équivalent « mode
 * téléphone » de l'impression thermique 58 mm du P501 (OPS Mobile, MEZ-94).
 *
 * Réutilise exactement le même ensemble de segments que le manifeste JSON
 * (ResolveManifestSegmentsAction) pour que le PDF et l'écran du convoyeur ne
 * divergent jamais. Ne contient JAMAIS de montant : aucune donnée de
 * paiement n'est chargée ni transmise à la vue.
 *
 * Sur une ligne internationale, refuse d'émettre le document si un
 * passager n'a pas toutes les informations obligatoires au contrôle
 * (BorderManifestIncompleteException, 422 `manifest_incomplete`).
 */
class GenerateBorderManifestPdfAction
{
    /** Libellés des types de pièce (valeurs de PassengerDocumentType). */
    private const array DOCUMENT_LABELS = [
        'passport' => 'Passeport',
        'national_id' => 'CNI',
        'biometric_card' => 'Carte biométrique',
        'consular_card' => 'Carte consulaire',
        'cip' => 'CIP',
    ];

    public function __construct(
        private readonly ResolveManifestSegmentsAction $resolveSegments,
        private readonly ComputeManifestVersionAction $computeVersion,
    ) {}

    /**
     * @return array{content: string, filename: string}
     *
     * @throws BorderManifestIncompleteException
     */
    public function execute(Departure $departure): array
    {
        $departure->loadMissing(['line:id,uuid,code,name,type', 'bus:id,uuid,registration_number']);
        $international = $departure->line->type === LineType::Internationale;

        [$segments, $ticketsByPassengerId, $statusesBySegmentId] = $this->resolveSegments->execute($departure);
        $manifestVersion = $this->computeVersion->execute($departure, $segments, $ticketsByPassengerId);

        $travelling = $segments->filter(function (ReservationSegment $segment) use ($statusesBySegmentId): bool {
            $status = $statusesBySegmentId[$segment->id] ?? null;

            return $status !== ManifestSegmentStatus::Cancelled
                && $status !== ManifestSegmentStatus::NoShow
                && $status !== ManifestSegmentStatus::EarlyDropOff;
        })->values();

        if ($travelling->isEmpty()) {
            throw BorderManifestIncompleteException::noPassengers();
        }

        $rows = [];
        $incomplete = [];

        foreach ($travelling as $index => $segment) {
            /** @var ReservationPassenger|null $passenger */
            $passenger = $segment->reservationPassenger;

            $name = $passenger === null ? '' : trim($passenger->first_name.' '.$passenger->last_name);
            $seat = (string) ($segment->seat->code ?? '');
            $destination = (string) ($segment->destinationStop->name ?? '');
            $nationality = $passenger?->nationality !== null ? strtoupper((string) $passenger->nationality) : '';
            $documentNumber = (string) ($passenger === null ? '' : $passenger->document_number);
            $documentType = $passenger?->document_type === null ? null : (string) enum_value($passenger->document_type);

            if ($international) {
                $missing = array_keys(array_filter([
                    'nom' => $name === '',
                    'nationalité' => $nationality === '',
                    'numéro de document' => $documentNumber === '',
                    'siège' => $seat === '',
                    'destination' => $destination === '',
                ]));

                if ($missing !== []) {
                    $incomplete[] = [
                        'name' => $name === '' ? 'Passager sans nom' : $name,
                        'seat' => $seat === '' ? '—' : $seat,
                        'missing' => $missing,
                    ];
                }
            }

            $rows[] = [
                'index' => $index + 1,
                'name' => $name,
                'seat' => $seat,
                'destination' => $destination,
                'nationality' => $nationality,
                'documentLabel' => $documentType === null ? '' : (self::DOCUMENT_LABELS[$documentType] ?? $documentType),
                'documentNumber' => $documentNumber,
                'birthDate' => $passenger?->birth_date?->format('d/m/Y') ?? '',
            ];
        }

        if ($incomplete !== []) {
            throw new BorderManifestIncompleteException($incomplete);
        }

        $html = view('manifests.border', [
            'international' => $international,
            'departureNumber' => strtoupper(substr((string) $departure->uuid, 0, 8)),
            'lineCode' => $departure->line->code,
            'lineName' => $departure->line->name,
            'busRegistration' => $departure->bus->registration_number ?? '—',
            'dateLabel' => $departure->departure_date?->format('d/m/Y') ?? '—',
            'timeLabel' => substr((string) $departure->departure_time, 0, 5),
            'passengerCount' => count($rows),
            'passengers' => $rows,
            'manifestVersion' => $manifestVersion,
            'generatedAtLabel' => now()->format('d/m/Y H:i'),
        ])->render();

        $lineCode = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $departure->line->code) ?: 'ligne';
        $date = $departure->departure_date?->format('Y-m-d') ?? 'depart';

        return [
            'content' => $this->renderPdf($html),
            'filename' => "manifeste-frontiere-{$lineCode}-{$date}.pdf",
        ];
    }

    protected function renderPdf(string $html): string
    {
        $profile = config('pdf.profiles.manifest', []);
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
            ->margins(...($profile['margins'] ?? [10, 10, 10, 10]))
            // Browsershot::timeout() attend des SECONDES ; config('pdf.timeout')
            // est exprimé en millisecondes (60000 = 60 s).
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

        if ($profile['wait_until_network_idle'] ?? false) {
            $browsershot->waitUntilNetworkIdle();
        }

        return $browsershot->pdf();
    }
}
