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
    public function __construct(
        ReservationSegment $resource,
        private readonly ?Ticket $ticket,
        private readonly ?ManifestSegmentStatus $segmentStatus,
        private readonly bool $includeBorderControlFields,
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
