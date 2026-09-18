<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PromotionScope: string
{
    use HasEnumUtilities;

    case Network = 'network';
    case Channel = 'channel';
    case Line = 'line';
    case Segment = 'segment';
}
