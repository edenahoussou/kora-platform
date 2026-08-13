<?php

namespace App\Modules\Ticketing\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends KoraModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }
}
