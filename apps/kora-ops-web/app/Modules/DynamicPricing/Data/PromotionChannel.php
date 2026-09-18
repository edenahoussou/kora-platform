<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PromotionChannel: string
{
    use HasEnumUtilities;

    case App = 'app';
    case Counter = 'counter';

    /**
     * Canaux de réservation couverts par le périmètre canal d'une promotion :
     * le guichet (vente physique KORA OPS) et l'application (vente en ligne).
     */
    public function covers(ReservationChannel $channel): bool
    {
        return match ($this) {
            self::Counter => in_array($channel, [ReservationChannel::Counter, ReservationChannel::Ops], true),
            self::App => in_array($channel, [ReservationChannel::Mobile, ReservationChannel::Online], true),
        };
    }
}
