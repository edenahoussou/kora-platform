<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Line extends KoraModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['countries_crossed' => 'array'];
    }
}
