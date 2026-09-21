<?php

namespace App\Modules\IdentityAccess\Data;

enum RoleCategory: string
{
    case Central = 'central';
    case Agency = 'agency';
    case Terrain = 'terrain';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
