<?php

namespace App\Modules\Boarding\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Codes fonctionnels exploitables par l'app OPS Mobile pour une
 * libération de segment refusée ou contradictoire. `TicketUnknown` n'est
 * jamais persisté (pas de ticket_id valide) ; les autres sont attachés à
 * un SeatReleaseEvent via released_segments/status.
 */
enum SeatReleaseFunctionalCode: string
{
    use HasEnumUtilities;

    case TicketUnknown = 'ticket_unknown';
    case TicketCancelled = 'ticket_cancelled';
    case WrongDeparture = 'wrong_departure';
    case StopNotCompatible = 'stop_not_compatible';
    case SegmentNotBoarded = 'segment_not_boarded';
    case SegmentAlreadyReleased = 'segment_already_released';
    case ReleaseConflict = 'seat_release_conflict';
    case DepartureNotYetClosable = 'departure_not_yet_closable';
    case SegmentNotEligibleForNoShow = 'segment_not_eligible_for_no_show';

    public function message(): string
    {
        return match ($this) {
            self::TicketUnknown => 'Billet inconnu.',
            self::TicketCancelled => 'Ce billet a été annulé.',
            self::WrongDeparture => 'Ce billet n’est pas valable pour ce départ.',
            self::StopNotCompatible => 'Cet arrêt n’est pas compatible avec la jambe et la destination initiale du billet.',
            self::SegmentNotBoarded => 'Ce billet n’a pas été embarqué sur ce départ — impossible de signaler une descente.',
            self::SegmentAlreadyReleased => 'Ce trajet a déjà été libéré.',
            self::ReleaseConflict => 'Ce trajet a déjà été libéré depuis un autre terminal.',
            self::DepartureNotYetClosable => 'Le no-show n’est possible qu’une fois le départ parti ou arrivé.',
            self::SegmentNotEligibleForNoShow => 'Ce billet a déjà été embarqué ou déjà libéré — ce n’est pas un no-show.',
        };
    }

    public function recommendedAction(): string
    {
        return match ($this) {
            self::TicketUnknown => 'Vérifiez le billet et réessayez.',
            self::TicketCancelled => 'Aucune action — le billet est annulé.',
            self::WrongDeparture => 'Vérifiez que vous êtes bien sur le bon départ pour ce billet.',
            self::StopNotCompatible => 'Sélectionnez un arrêt situé entre l’embarquement et la destination du billet.',
            self::SegmentNotBoarded => 'Embarquez d’abord le billet (scan) avant de signaler une descente anticipée.',
            self::SegmentAlreadyReleased => 'Consultez l’historique des libérations avant de recommencer.',
            self::ReleaseConflict => 'Vérifiez auprès de l’autre terminal avant de recommencer — la libération d’origine fait foi.',
            self::DepartureNotYetClosable => 'Attendez que le départ soit marqué parti ou arrivé.',
            self::SegmentNotEligibleForNoShow => 'Vérifiez le statut du billet avant de signaler un no-show.',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::TicketUnknown => 404,
            self::TicketCancelled, self::WrongDeparture, self::StopNotCompatible, self::DepartureNotYetClosable => 422,
            self::SegmentNotBoarded, self::SegmentAlreadyReleased, self::ReleaseConflict, self::SegmentNotEligibleForNoShow => 409,
        };
    }
}
