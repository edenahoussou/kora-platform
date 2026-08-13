<?php

namespace App\Modules\IdentityAccess\Data;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Locked = 'locked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
