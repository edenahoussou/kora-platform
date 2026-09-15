<?php

namespace App\Modules\Boarding\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Codes fonctionnels exploitables par l'app OPS Mobile pour un scan
 * d'embarquement refusé ou en conflit. `QrUnknown` n'est jamais persisté
 * (aucun billet résolu, donc aucun ticket_id valide pour boarding_events) ;
 * les cinq autres sont attachés à un BoardingEvent (Rejected ou Conflict)
 * via la colonne conflict_payload.
 */
enum BoardingFunctionalCode: string
{
    use HasEnumUtilities;

    case QrUnknown = 'qr_unknown';
    case TicketCancelled = 'ticket_cancelled';
    case WrongDeparture = 'wrong_departure';
    case LegNotActive = 'leg_not_active';
    case AlreadyBoarded = 'ticket_already_boarded';
    case Conflict = 'boarding_conflict';

    public function message(): string
    {
        return match ($this) {
            self::QrUnknown => 'Billet inconnu ou signature invalide.',
            self::TicketCancelled => 'Ce billet a été annulé.',
            self::WrongDeparture => 'Ce billet n’est pas valable pour ce départ.',
            self::LegNotActive => 'Cette jambe du billet n’est pas active (annulée, expirée ou pas encore émise).',
            self::AlreadyBoarded => 'Ce billet a déjà été embarqué sur ce départ.',
            self::Conflict => 'Ce billet a déjà été embarqué sur un autre terminal.',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::QrUnknown => 404,
            self::TicketCancelled, self::WrongDeparture, self::LegNotActive => 422,
            self::AlreadyBoarded, self::Conflict => 409,
        };
    }
}
