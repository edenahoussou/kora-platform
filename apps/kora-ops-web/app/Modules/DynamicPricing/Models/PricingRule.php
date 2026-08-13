<?php

namespace App\Modules\DynamicPricing\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingRule extends KoraModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
