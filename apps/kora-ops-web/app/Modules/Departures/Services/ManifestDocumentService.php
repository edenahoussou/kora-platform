<?php

namespace App\Modules\Departures\Services;

use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use App\Modules\BusNetwork\Data\LineType;
use App\Modules\Departures\Actions\ComputeManifestVersionAction;
use App\Modules\Departures\Actions\ResolveManifestSegmentsAction;
use App\Modules\Departures\Data\ManifestSegmentStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Support\Services\DocumentBrandingService;
use App\Modules\Ticketing\Models\Ticket;

use function Illuminate\Support\enum_value;

/**
 * Construit le payload de la vue Blade `manifests.manifest` — le manifeste
 * passagers A4 paysage servi au web (impression) et, à terme, au PDF
 * téléchargé par OPS Web comme par OPS Mobile (remplace le manifeste
 * frontière actuel via Browsershot sur la même vue).
 *
 * Réutilise ResolveManifestSegmentsAction : la même liste de segments que
 * le manifeste du convoyeur — web, mobile et imprimé ne divergent jamais.
 *
 * Les données sensibles (numéro de pièce, téléphone) sont masquées ici —
 * la vue ne reçoit que les 3 derniers caractères, jamais la valeur brute.
 */
class ManifestDocumentService
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
        private readonly DepartureStopTimeResolver $stopTimeResolver,
        private readonly DocumentBrandingService $branding,
    ) {}

    /** @return array<string, mixed> */
    public function payload(Departure $departure): array
    {
        $departure->loadMissing([
            'line:id,uuid,code,name,type',
            'line.stops' => fn ($q) => $q->where('status', 'active')->orderBy('stop_order')->with('physicalStop:id,uuid,name,city'),
            'bus:id,uuid,registration_number,seat_count',
            'driver:id,uuid,name',
            'conductor:id,uuid,name',
        ]);

        [$segments, $ticketsByPassengerId, $statusesBySegmentId] = $this->resolveSegments->execute($departure);

        $international = $departure->line->type === LineType::Internationale;
        $stops = $departure->line->stops;
        $originStop = $stops->first();
        $destinationStop = $stops->last();

        $summary = ['total' => $segments->count(), 'boarded' => 0, 'pending' => 0, 'absent' => 0, 'cancelled' => 0];
        $passengers = [];
        $incomplete = [];
        $travellingCount = 0;

        foreach ($segments->values() as $index => $segment) {
            /** @var ReservationSegment $segment */
            $status = $statusesBySegmentId[$segment->id] ?? ManifestSegmentStatus::Paid;

            match ($status) {
                ManifestSegmentStatus::Boarded, ManifestSegmentStatus::EarlyDropOff => $summary['boarded']++,
                ManifestSegmentStatus::Paid => $summary['pending']++,
                ManifestSegmentStatus::NoShow => $summary['absent']++,
                ManifestSegmentStatus::Cancelled => $summary['cancelled']++,
            };

            // Voyageurs réellement attendus à bord — même filtre que
            // l'ancien manifeste frontière : ni annulés, ni no-show, ni
            // descendus en route ne bloquent ni ne valident le document.
            if (! in_array($status, [
                ManifestSegmentStatus::Cancelled,
                ManifestSegmentStatus::NoShow,
                ManifestSegmentStatus::EarlyDropOff,
            ], true)) {
                $travellingCount++;

                if ($international) {
                    $missing = $this->missingBorderFields($segment);
                    if ($missing !== []) {
                        $passenger = $segment->reservationPassenger;
                        $incomplete[] = [
                            'name' => $passenger === null || trim($passenger->first_name.' '.$passenger->last_name) === ''
                                ? 'Passager sans nom'
                                : trim($passenger->first_name.' '.$passenger->last_name),
                            'seat' => $segment->seat->code !== '' ? $segment->seat->code : '—',
                            'missing' => $missing,
                        ];
                    }
                }
            }

            $passengers[] = $this->row($segment, $index, $status, $ticketsByPassengerId, $departure);
        }

        return [
            'international' => $international,
            'logoDataUri' => $this->branding->logoDataUri(),
            'company' => $this->branding->company(),
            'document' => [
                'number' => $this->documentNumber($departure),
                'version' => $this->computeVersion->execute($departure, $segments, $ticketsByPassengerId),
                'generatedAt' => now()->format('d/m/Y à H:i'),
            ],
            'departure' => [
                'lineCode' => $departure->line->code,
                'lineName' => $departure->line->name,
                'lineTypeLabel' => $departure->line->type->translatedLabel(),
                'originCity' => $originStop?->physicalStop->city ?? $originStop->city ?? $originStop->name ?? '—',
                'destinationCity' => $destinationStop?->physicalStop->city ?? $destinationStop->city ?? $destinationStop->name ?? '—',
                'dateLabel' => $departure->departure_date?->format('d/m/Y') ?? '—',
                'timeLabel' => substr((string) $departure->departure_time, 0, 5),
                'busRegistration' => $departure->bus->registration_number ?? '—',
                'driverName' => $departure->driver?->name,
                'conductorName' => $departure->conductor?->name,
                'originPoint' => $originStop?->physicalStop->name ?? $originStop?->name,
                'seatCount' => $departure->bus->seat_count ?? 0,
                'stopsLabel' => $stops->map(fn ($stop) => $stop->physicalStop->city ?? $stop->city ?? $stop->name)->implode(' · '),
                'statusLabel' => $departure->status->translatedLabel(),
            ],
            'summary' => $summary,
            'passengers' => $passengers,
            // Garde-fous du document officiel : lus par
            // GenerateManifestPdfAction pour refuser un manifeste vide ou
            // incomplet sur une ligne internationale (422 manifest_incomplete).
            'travellingCount' => $travellingCount,
            'incompletePassengers' => $incomplete,
        ];
    }

    /**
     * Champs obligatoires au contrôle frontière pour un segment voyageur —
     * vérifiés sur les données BRUTES (avant tout masquage).
     *
     * @return list<string>
     */
    private function missingBorderFields(ReservationSegment $segment): array
    {
        $passenger = $segment->reservationPassenger;

        return array_keys(array_filter([
            'nom' => $passenger === null || trim($passenger->first_name.' '.$passenger->last_name) === '',
            'nationalité' => $passenger === null || trim((string) $passenger->nationality) === '',
            'numéro de document' => $passenger === null || trim((string) $passenger->document_number) === '',
            'siège' => trim((string) $segment->seat->code) === '',
            'destination' => trim((string) $segment->destinationStop->name) === '',
        ]));
    }

    /**
     * @param  array<int, Ticket>  $ticketsByPassengerId
     * @return array<string, string|int|null>
     */
    private function row(ReservationSegment $segment, int $index, ManifestSegmentStatus $status, array $ticketsByPassengerId, Departure $departure): array
    {
        $passenger = $segment->reservationPassenger;
        $ticket = $passenger !== null ? ($ticketsByPassengerId[$passenger->id] ?? null) : null;
        $documentType = $passenger?->document_type === null ? null : (string) enum_value($passenger->document_type);
        // Le resolver rend des instants UTC — le document affiche l'heure
        // locale du départ (montée/descente lisibles par l'équipage).
        $timezone = $departure->departure_timezone ?? config('app.timezone');

        return [
            'index' => $index + 1,
            'seat' => $segment->seat->code,
            'name' => $passenger === null ? '—' : trim($passenger->first_name.' '.$passenger->last_name),
            'ticketRef' => $ticket->short_code ?? $segment->reservation->reference ?? '—',
            'phone' => self::mask($passenger?->phone),
            'nationality' => $passenger?->nationality !== null ? strtoupper((string) $passenger->nationality) : '',
            'documentLabel' => $documentType === null ? '' : (self::DOCUMENT_LABELS[$documentType] ?? $documentType),
            'documentNumber' => self::mask($passenger?->document_number),
            'birthDate' => $passenger?->birth_date?->format('d/m/Y') ?? '—',
            'originStop' => $segment->originStop->name,
            'originTime' => $this->stopTimeResolver->departureAt($departure, $segment->originStop)?->setTimezone($timezone)->format('H:i') ?? '—',
            'destinationStop' => $segment->destinationStop->name,
            'destinationTime' => $this->stopTimeResolver->arrivalAt($departure, $segment->destinationStop)?->setTimezone($timezone)->format('H:i') ?? '—',
            'boardingLabel' => $this->boardingLabel($status),
            'boardingTone' => $this->boardingTone($status),
        ];
    }

    private function boardingLabel(ManifestSegmentStatus $status): string
    {
        return match ($status) {
            ManifestSegmentStatus::Boarded => 'Embarqué',
            ManifestSegmentStatus::Paid => 'À contrôler',
            ManifestSegmentStatus::NoShow => 'Absent',
            ManifestSegmentStatus::EarlyDropOff => 'Descendu en route',
            ManifestSegmentStatus::Cancelled => 'Annulé',
        };
    }

    private function boardingTone(ManifestSegmentStatus $status): string
    {
        return match ($status) {
            ManifestSegmentStatus::Boarded => 'green',
            ManifestSegmentStatus::Paid => 'gold',
            ManifestSegmentStatus::NoShow => 'red',
            ManifestSegmentStatus::EarlyDropOff, ManifestSegmentStatus::Cancelled => 'muted',
        };
    }

    /**
     * Masque une donnée sensible — seuls les 3 derniers caractères restent
     * lisibles (même règle que pour les numéros de carte bancaire).
     */
    private static function mask(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return str_repeat('•', max(strlen($value) - 3, 0)).substr($value, -3);
    }

    private function documentNumber(Departure $departure): string
    {
        $lineCode = (string) $departure->line->code;
        $date = $departure->departure_date?->format('Ymd') ?? 'depart';
        $time = substr(str_replace(':', '', (string) $departure->departure_time), 0, 4);

        return "MAN-{$lineCode}-{$date}-{$time}";
    }
}
