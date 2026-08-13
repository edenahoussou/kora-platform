<?php

namespace App\Modules\IdentityAccess\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends KoraModel
{
    use SoftDeletes;
}
