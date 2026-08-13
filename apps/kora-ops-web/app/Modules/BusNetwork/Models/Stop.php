<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stop extends KoraModel
{
    use SoftDeletes;
}
