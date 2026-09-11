<?php

namespace App\Modules\BusNetwork\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum MapProvider: string
{
    use HasEnumUtilities;

    case OpenStreetMap = 'openstreetmap';
    case Google = 'google';
}
