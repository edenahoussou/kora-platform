<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum TaxRuleStatus: string
{
    use HasEnumUtilities;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
