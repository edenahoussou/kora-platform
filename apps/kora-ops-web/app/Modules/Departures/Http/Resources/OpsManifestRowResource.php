<?php

namespace App\Modules\Departures\Http\Resources;

use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Data\ManifestSegmentStatus;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

/**
 * Une ligne du manifeste OPS Mobile : un segment + son billet. Ne renvoie
 * jamais de prix, montant, ou identifiant numérique interne — uniquement
 * UUID et codes métier. Les champs de contrôle frontière (document,
 * nationalité, naissance, fièvre jaune) ne sont inclus que pour les
 * départs sur ligne internationale ($includeBorderControlFields).
 *
 * @mixin ReservationSegment
 */
class OpsManifestRowResource extends JsonResource
{
    /**
     * @param  array<int, string>  $boardingEventUuidsByTicketId  UUID du
     *                                                            dernier BoardingEvent accepté pour ce départ, indexé par
     *                                                            ticket_id — voir OpsDepartureController::manifest(). Permet
     *                                                            à l'app OPS Mobile de proposer l'annulation exceptionnelle
     *                                                            d'un embarquement (POST /ops/boarding/{uuid}/reverse) sans
     *                                                            connaître déjà cet UUID (le manifeste ne l'exposait pas
     *                                                            jusqu'ici). Reste renseigné même après une annulation — voir
     *                                                            la note sur `boardingEventUuid` dans toArray().
     */
    public function __construct(
        ReservationSegment $resource,
        private readonly ?Ticket $ticket,
        private readonly ?ManifestSegmentStatus $segmentStatus,
        private readonly bool $includeBorderControlFields,
        private readonly array $boardingEventUuidsByTicketId = [],
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ReservationPassenger|null $passenger */
        $passenger = $this->reservationPassenger;

        return [
            'ticketUuid' => $this->ticket?->uuid,
            'shortCode' => $this->ticket?->short_code,
            'name' => $passenger === null ? null : trim($passenger->first_name.' '.$passenger->last_name),
            'seatCode' => $this->seat?->code,
            'originStop' => $this->originStop === null ? null : [
                'uuid' => $this->originStop->uuid,
                'name' => $this->originStop->name,
            ],
            'destinationStop' => $this->destinationStop === null ? null : [
                'uuid' => $this->destinationStop->uuid,
                'name' => $this->destinationStop->name,
            ],
            'segmentStatus' => enum_value($this->segmentStatus),
            'ticketStatus' => $this->ticket === null ? null : enum_value($this->ticket->status),
            // `null` si jamais embarqué sur ce départ. Note : après une
            // annulation exceptionnelle (reverse), le BoardingEvent
            // d'origine garde volontairement son statut `accepted`
            // (jamais modifié — voir ReverseBoardingEventAction) ; seul
            // `segmentStatus` reflète l'annulation (repasse à "ticketed").
            // Le mobile doit donc croiser les deux : ne proposer
            // "annuler l'embarquement" que si `segmentStatus === 'boarded'`.
            'boardingEventUuid' => $this->ticket === null
                ? null
                : ($this->boardingEventUuidsByTicketId[$this->ticket->id] ?? null),
            ...($this->includeBorderControlFields ? [
                'document' => $passenger === null || $passenger->document_type === null ? null : [
                    'type' => enum_value($passenger->document_type),
                    'number' => $passenger->document_number,
                ],
                'nationality' => $passenger?->nationality,
                'birthDate' => $passenger?->birth_date?->toDateString(),
                'yellowFeverVaccinated' => $passenger?->yellow_fever_vaccinated,
            ] : []),
        ];
    }
}
