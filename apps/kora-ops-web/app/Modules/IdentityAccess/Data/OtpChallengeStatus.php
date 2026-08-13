<?php

namespace App\Modules\IdentityAccess\Data;

enum OtpChallengeStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Consumed = 'consumed';
    case Expired = 'expired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
