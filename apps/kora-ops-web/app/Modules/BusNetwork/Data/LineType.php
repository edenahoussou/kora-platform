<?php

namespace App\Modules\BusNetwork\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum LineType: string
{
    use HasEnumUtilities;

    case Nationale = 'nationale';
    case Internationale = 'internationale';
}
