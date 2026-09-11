<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ticketing\Actions\GenerateTicketQrCodeAction;
use Illuminate\Http\Response;

/**
 * Static preview of the ticket Blade template with mock data.
 *
 * Allows visual validation of all ticket formats (one-way, round-trip,
 * multi-leg, group) before wiring up real data.
 *
 * Access via /tickets/preview/{scenario} where scenario is one of:
 *   - one-way
 *   - round-trip
 *   - multi-leg
 *   - group
 *   - no-fiscal
 */
class TicketPreviewController extends Controller
{
    public function __construct(
        private readonly GenerateTicketQrCodeAction $generateQr,
    ) {}

    public function show(string $scenario): Response
    {
        $data = $this->scenarioData($scenario);
        $data['qrSvg'] = $this->generateQr->execute($data['shortCode']);
        $data['isPdf'] = false;
        $data['pdfUrl'] = '#';
        $data['ticketUuid'] = 'preview-'.$scenario;

        return response()->view('tickets.show', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function scenarioData(string $scenario): array
    {
        return match ($scenario) {
            'one-way' => $this->oneWay(),
            'round-trip' => $this->roundTrip(),
            'multi-leg' => $this->multiLeg(),
            'group' => $this->group(),
            'no-fiscal' => $this->noFiscal(),
            default => $this->oneWay(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function oneWay(): array
    {
        return [
            'shortCode' => 'KE-7F3A9B2C',
            'statusLabel' => 'Payé',
            'originCity' => 'COTONOU',
            'destinationCity' => 'PARAKOU',
            'passengerName' => 'Aïcha IBRAHIM',
            'passengerPhone' => '+229 01 97 42 18 60',
            'passengerDocument' => 'BJ-2938475',
            'legs' => [
                [
                    'directionLabel' => 'Aller',
                    'originCity' => 'Cotonou',
                    'destinationCity' => 'Parakou',
                    'dateLabel' => '12/09/2026',
                    'departureTime' => '07:00',
                    'lineCode' => 'KX-101',
                    'busRegistration' => 'BJ-4521-AB',
                    'seatCode' => '12A',
                ],
            ],
            'legsCount' => 1,
            'mecefUid' => 'MECEF-2026-09-08-A1B2C3D4',
            'amountLabel' => '15 000 XOF',
            'issuedAtLabel' => '10/09/2026',
            'agencyName' => 'KORA Express — Agence Cotonou',
            'channelLabel' => 'Vente au guichet',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function roundTrip(): array
    {
        return [
            'shortCode' => 'KE-9R2K7M1',
            'statusLabel' => 'Payé',
            'originCity' => 'COTONOU',
            'destinationCity' => 'PARAKOU',
            'passengerName' => 'Koffi MENSAH',
            'passengerPhone' => '+229 01 52 33 44 55',
            'passengerDocument' => 'BJ-1928374',
            'legs' => [
                [
                    'directionLabel' => 'Aller',
                    'originCity' => 'Cotonou',
                    'destinationCity' => 'Parakou',
                    'dateLabel' => '12/09/2026',
                    'departureTime' => '07:00',
                    'lineCode' => 'KX-101',
                    'busRegistration' => 'BJ-4521-AB',
                    'seatCode' => '14C',
                ],
                [
                    'directionLabel' => 'Retour',
                    'originCity' => 'Parakou',
                    'destinationCity' => 'Cotonou',
                    'dateLabel' => '15/09/2026',
                    'departureTime' => '14:00',
                    'lineCode' => 'KX-102',
                    'busRegistration' => 'BJ-7821-CD',
                    'seatCode' => '03A',
                ],
            ],
            'legsCount' => 2,
            'mecefUid' => 'MECEF-2026-09-08-X7Y8Z9W0',
            'amountLabel' => '28 000 XOF',
            'issuedAtLabel' => '10/09/2026',
            'agencyName' => 'KORA Express — Agence Cotonou',
            'channelLabel' => 'Vente au guichet',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function multiLeg(): array
    {
        return [
            'shortCode' => 'KE-4M8L2N6',
            'statusLabel' => 'Payé',
            'originCity' => 'COTONOU',
            'destinationCity' => 'NATITINGOU',
            'passengerName' => 'Fatima OUEDRAOGO',
            'passengerPhone' => '+229 01 88 77 66 55',
            'passengerDocument' => 'BJ-5647382',
            'legs' => [
                [
                    'directionLabel' => 'Aller',
                    'originCity' => 'Cotonou',
                    'destinationCity' => 'Parakou',
                    'dateLabel' => '12/09/2026',
                    'departureTime' => '07:00',
                    'lineCode' => 'KX-101',
                    'busRegistration' => 'BJ-4521-AB',
                    'seatCode' => '08B',
                ],
                [
                    'directionLabel' => 'Aller',
                    'originCity' => 'Parakou',
                    'destinationCity' => 'Djougou',
                    'dateLabel' => '12/09/2026',
                    'departureTime' => '13:30',
                    'lineCode' => 'KX-205',
                    'busRegistration' => 'BJ-3344-EF',
                    'seatCode' => '05A',
                ],
                [
                    'directionLabel' => 'Aller',
                    'originCity' => 'Djougou',
                    'destinationCity' => 'Natitingou',
                    'dateLabel' => '12/09/2026',
                    'departureTime' => '17:00',
                    'lineCode' => 'KX-308',
                    'busRegistration' => 'BJ-9988-GH',
                    'seatCode' => '02C',
                ],
            ],
            'legsCount' => 3,
            'mecefUid' => 'MECEF-2026-09-08-M3L4T5I6',
            'amountLabel' => '42 000 XOF',
            'issuedAtLabel' => '10/09/2026',
            'agencyName' => 'KORA Express — Agence Cotonou',
            'channelLabel' => 'Vente au guichet',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function group(): array
    {
        return [
            'shortCode' => 'KE-G1R0U7P',
            'statusLabel' => 'Payé',
            'originCity' => 'COTONOU',
            'destinationCity' => 'PARAKOU',
            'passengerName' => 'Groupe École Saint-Joseph (12 passagers)',
            'passengerPhone' => '+229 01 44 55 66 77',
            'passengerDocument' => 'N/A',
            'legs' => [
                [
                    'directionLabel' => 'Aller',
                    'originCity' => 'Cotonou',
                    'destinationCity' => 'Parakou',
                    'dateLabel' => '12/09/2026',
                    'departureTime' => '06:00',
                    'lineCode' => 'KX-101',
                    'busRegistration' => 'BJ-4521-AB',
                    'seatCode' => '1-12',
                ],
            ],
            'legsCount' => 1,
            'mecefUid' => 'MECEF-2026-09-08-G7R0P1S2',
            'amountLabel' => '180 000 XOF',
            'issuedAtLabel' => '10/09/2026',
            'agencyName' => 'KORA Express — Agence Cotonou',
            'channelLabel' => 'Vente au guichet',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noFiscal(): array
    {
        return [
            'shortCode' => 'KE-NF1C0AL',
            'statusLabel' => 'Payé',
            'originCity' => 'COTONOU',
            'destinationCity' => 'PORTO-NOVO',
            'passengerName' => 'Jean DOSSOU',
            'passengerPhone' => '+229 01 22 33 44 55',
            'passengerDocument' => null,
            'legs' => [
                [
                    'directionLabel' => 'Aller',
                    'originCity' => 'Cotonou',
                    'destinationCity' => 'Porto-Novo',
                    'dateLabel' => '12/09/2026',
                    'departureTime' => '09:00',
                    'lineCode' => 'KX-051',
                    'busRegistration' => 'BJ-2211-IJ',
                    'seatCode' => '06D',
                ],
            ],
            'legsCount' => 1,
            'mecefUid' => null,
            'amountLabel' => '5 000 XOF',
            'issuedAtLabel' => '10/09/2026',
            'agencyName' => 'KORA Express — Agence Cotonou',
            'channelLabel' => 'Vente en ligne',
        ];
    }
}
