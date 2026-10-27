<?php

namespace App\Modules\FleetLite\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Relevé compteur du carnet de bord : au départ ou à l'arrivée du voyage.
 */
enum OdometerReadingKind: string
{
    use HasEnumUtilities;

    case Start = 'start';
    case End = 'end';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Départ',
            self::End => 'Arrivée',
        };
    }
}
