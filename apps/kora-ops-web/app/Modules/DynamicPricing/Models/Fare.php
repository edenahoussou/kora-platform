<?php

namespace App\Modules\DynamicPricing\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fare extends KoraModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }
}
