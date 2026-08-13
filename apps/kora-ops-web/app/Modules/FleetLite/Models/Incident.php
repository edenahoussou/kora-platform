<?php

namespace App\Modules\FleetLite\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends KoraModel
{
    use SoftDeletes;
}
