<?php

namespace App\Modules\IdentityAccess\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum RoleCategory: string
{
    use HasEnumUtilities;

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
