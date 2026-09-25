<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Modules\Departures\Actions\GenerateManifestPdfAction;
use App\Modules\Departures\Models\Departure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Prévisualisation du manifeste PDF (A4) avec des données fictives.
 *
 * TEMPORAIRE — sert uniquement à valider la structure visuelle du nouveau
 * manifeste. La même vue est servie par GenerateManifestPdfAction et a
 * remplacé l'ancien manifeste frontière : web (manifest/{uuid}/pdf) et
 * OPS Mobile (route api ops.departures.manifest-pdf) produisent le même
 * document.
 *
 * `?download=1` sort le même document en PDF binaire pour tester le rendu
 * Browsershot réel (pagination, marges, polices) sur données fictives.
 */
class ManifestPdfPreviewController
{
    public function __invoke(Request $request, GenerateManifestPdfAction $generatePdf): Response
    {
        Gate::authorize('viewAny', Departure::class);

        if ($request->boolean('download')) {
            try {
                $pdf = $generatePdf->renderPayload($this->mockPayload(), 'manifeste-apercu.pdf');
            } catch (Throwable $exception) {
                report($exception);
                abort(500, "Le manifeste n'a pas pu être généré.");
            }

            return response($pdf['content'], Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
                'Content-Length' => (string) strlen($pdf['content']),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->view('manifests.manifest', [
            ...$this->mockPayload(),
            'downloadUrl' => route('manifest.pdf-preview', ['download' => 1]),
        ]);
    }

    /**
     * Jeu de données fictif — reflète le contrat exact que l'action réelle
     * fournira à la vue (société, document, départ, compteurs, passagers).
     *
     * @return array<string, mixed>
     */
    private function mockPayload(): array
    {
        return [
            'international' => true,
            'logoDataUri' => 'data:image/png;base64,'.base64_encode(
                (string) file_get_contents(public_path('kora-logo-sidebar.png')),
            ),
            'company' => [
                'name' => 'KORA EXPRESS',
                'tagline' => 'Transport interurbain de voyageurs — Bénin · Togo · Côte d’Ivoire',
                'addressLines' => ['Carrefour Cadjèhoun, Cotonou — Bénin'],
                'contacts' => ['+229 01 46 00 00 00', 'ops@kora.bj'],
                'ifu' => '0202380865521',
                'nim' => 'EM0200000001',
            ],
            'document' => [
                'number' => 'MAN-KE301-3007',
                'version' => 'v3',
                'generatedAt' => '30/07/2026 à 04:52',
            ],
            'departure' => [
                'lineCode' => 'L1 · KE 301',
                'lineName' => 'Porto-Novo → Abidjan via Lomé et Accra',
                'lineTypeLabel' => 'Internationale',
                'originCity' => 'Porto-Novo',
                'destinationCity' => 'Abidjan',
                'dateLabel' => '30/07/2026',
                'timeLabel' => '05:30',
                'busRegistration' => 'BJ 6178 RB',
                'driverName' => 'A. Codjo',
                'conductorName' => 'F. Hounkpè',
                'originPoint' => 'Quai 2 — Gare Porto-Novo',
                'seatCount' => 55,
                'stopsLabel' => 'Porto-Novo · Cotonou · Ouidah · Hillacondji · Lomé · Accra · Abidjan',
                'statusLabel' => 'Embarquement en cours',
            ],
            'summary' => [
                'total' => 24,
                'boarded' => 18,
                'pending' => 4,
                'absent' => 2,
            ],
            'passengers' => $this->mockPassengers(),
        ];
    }

    /**
     * @return list<array<string, string|int>>
     */
    private function mockPassengers(): array
    {
        $rows = [
            ['2A', 'Koffi Mensah', 'BÉNINOISE', 'CNI', 'BJ-48215-2021', 'Porto-Novo Gare', '05:30', 'Abidjan Adjame', '17:40', 'EMBARQUÉ', 'green'],
            ['2B', 'Ama Kouadio', 'IVOIRIENNE', 'Passeport', 'CI-92841', 'Porto-Novo Gare', '05:30', 'Abidjan Adjame', '17:40', 'EMBARQUÉ', 'green'],
            ['2C', 'Kwame Asare', 'GHANÉENNE', 'Passeport', 'GH-18472', 'Cotonou Cadjèhoun', '06:15', 'Accra Circle', '11:20', 'EMBARQUÉ', 'green'],
            ['2D', 'Sena Lawson', 'TOGOLAISE', 'CNI', 'TG-62814', 'Ouidah', '07:05', 'Lomé Tokoin', '08:45', 'EMBARQUÉ', 'green'],
            ['3A', 'Aïcha Hounkpè', 'BÉNINOISE', 'CIP', 'BJ-77102', 'Porto-Novo Gare', '05:30', 'Lomé Tokoin', '08:45', 'EMBARQUÉ', 'green'],
            ['3B', 'Jean-Baptiste Kora', 'BÉNINOISE', 'CNI', 'BJ-55230-2019', 'Cotonou Cadjèhoun', '06:15', 'Abidjan Adjame', '17:40', 'À CONTRÔLER', 'gold'],
            ['3C', 'Mariam Bio', 'NIGÉRIANE', 'Passeport', 'NG-A0447213', 'Cotonou Cadjèhoun', '06:15', 'Accra Circle', '11:20', 'EMBARQUÉ', 'green'],
            ['3D', 'Yaw Darko', 'GHANÉENNE', 'Carte consulaire', 'GH-CC-2099', 'Hillacondji', '08:10', 'Accra Circle', '11:20', 'EMBARQUÉ', 'green'],
            ['4A', 'Sègla Akplogan', 'BÉNINOISE', 'CNI', 'BJ-33098-2018', 'Porto-Novo Gare', '05:30', 'Aného', '08:00', 'EMBARQUÉ', 'green'],
            ['4B', 'Adjoua N’Guessan', 'IVOIRIENNE', 'Passeport', 'CI-AD77190', 'Cotonou Cadjèhoun', '06:15', 'Abidjan Adjame', '17:40', 'EMBARQUÉ', 'green'],
            ['4C', 'Tunde Balogun', 'NIGÉRIANE', 'Passeport', 'NG-B1120304', 'Ouidah', '07:05', 'Lomé Tokoin', '08:45', 'À CONTRÔLER', 'gold'],
            ['4D', 'Elom Gbedji', 'TOGOLAISE', 'CNI', 'TG-88130', 'Hillacondji', '08:10', 'Lomé Tokoin', '08:45', 'EMBARQUÉ', 'green'],
            ['5A', 'Fatou Ndiaye', 'SÉNÉGALAISE', 'Passeport', 'SN-DE55217', 'Porto-Novo Gare', '05:30', 'Abidjan Adjame', '17:40', 'EMBARQUÉ', 'green'],
            ['5B', 'Kossi Agbeko', 'TOGOLAISE', 'CNI', 'TG-41027', 'Ouidah', '07:05', 'Accra Circle', '11:20', 'EMBARQUÉ', 'green'],
            ['5C', 'Rachid Ouédraogo', 'BURKINABÈ', 'CIP', 'BF-90012', 'Cotonou Cadjèhoun', '06:15', 'Abidjan Adjame', '17:40', 'ABSENT', 'red'],
            ['5D', 'Nadia Sossou', 'BÉNINOISE', 'CNI', 'BJ-66481-2022', 'Porto-Novo Gare', '05:30', 'Abidjan Adjame', '17:40', 'EMBARQUÉ', 'green'],
            ['6A', 'Moussa Traoré', 'MALIENNE', 'Passeport', 'ML-771889', 'Cotonou Cadjèhoun', '06:15', 'Abidjan Adjame', '17:40', 'EMBARQUÉ', 'green'],
            ['6B', 'Afi Mensah', 'TOGOLAISE', 'Carte biométrique', 'TG-BM-55214', 'Hillacondji', '08:10', 'Lomé Tokoin', '08:45', 'À CONTRÔLER', 'gold'],
            ['6C', 'Ibrahim Sawadogo', 'BURKINABÈ', 'Passeport', 'BF-330018', 'Porto-Novo Gare', '05:30', 'Accra Circle', '11:20', 'EMBARQUÉ', 'green'],
            ['6D', 'Grâce Ekoué', 'TOGOLAISE', 'CNI', 'TG-29517', 'Hillacondji', '08:10', 'Accra Circle', '11:20', 'EMBARQUÉ', 'green'],
            ['7A', 'Pascal Houngnibo', 'BÉNINOISE', 'CNI', 'BJ-11842-2020', 'Porto-Novo Gare', '05:30', 'Abidjan Adjame', '17:40', 'EMBARQUÉ', 'green'],
            ['7B', 'Chinwe Okafor', 'NIGÉRIANE', 'Passeport', 'NG-C5541288', 'Cotonou Cadjèhoun', '06:15', 'Abidjan Adjame', '17:40', 'À CONTRÔLER', 'gold'],
            ['7C', 'Yaw Owusu', 'GHANÉENNE', 'Passeport', 'GH-552871', 'Ouidah', '07:05', 'Accra Circle', '11:20', 'EMBARQUÉ', 'green'],
            ['7D', 'Akossiwa Dzakpasu', 'TOGOLAISE', 'CNI', 'TG-77005', 'Hillacondji', '08:10', 'Lomé Tokoin', '08:45', 'ABSENT', 'red'],
        ];

        return array_map(static function (array $row, int $index): array {
            [$seat, $name, $nationality, $docLabel, $docNumber, $origin, $originTime, $destination, $destinationTime, $boardingLabel, $boardingTone] = $row;

            return [
                'index' => $index + 1,
                'seat' => $seat,
                'name' => $name,
                'ticketRef' => 'KE-20260730-'.str_pad((string) ($index + 1180), 4, '0', STR_PAD_LEFT),
                'phone' => self::mask('+229 01 '.str_pad((string) (90000000 + $index * 331177), 8, '0', STR_PAD_LEFT)),
                'nationality' => $nationality,
                'documentLabel' => $docLabel,
                // Donnée sensible : la vue ne reçoit que les 3 derniers
                // caractères — jamais le numéro de pièce en clair.
                'documentNumber' => self::mask($docNumber),
                'birthDate' => sprintf('%02d/%02d/%d', ($index % 28) + 1, ($index % 12) + 1, 1964 + ($index * 2) % 40),
                'originStop' => $origin,
                'originTime' => $originTime,
                'destinationStop' => $destination,
                'destinationTime' => $destinationTime,
                'boardingLabel' => $boardingLabel,
                'boardingTone' => $boardingTone,
            ];
        }, $rows, array_keys($rows));
    }

    /**
     * Masque une donnée sensible — seuls les 3 derniers caractères restent
     * lisibles (même règle que pour les numéros de carte bancaire).
     */
    private static function mask(string $value): string
    {
        return str_repeat('•', max(strlen($value) - 3, 0)).substr($value, -3);
    }
}
