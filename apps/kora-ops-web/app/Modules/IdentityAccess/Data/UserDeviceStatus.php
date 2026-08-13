<?php

namespace App\Modules\IdentityAccess\Data;

enum UserDeviceStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
