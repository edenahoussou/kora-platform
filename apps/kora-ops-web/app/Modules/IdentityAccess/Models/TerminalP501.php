<?php

namespace App\Modules\IdentityAccess\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class TerminalP501 extends KoraModel
{
    use SoftDeletes;

    protected $table = 'terminals_p501';
}
