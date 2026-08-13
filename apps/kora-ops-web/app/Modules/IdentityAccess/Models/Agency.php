<?php

namespace App\Modules\IdentityAccess\Models;

use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'city',
        'country',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => AgencyStatus::class];
    }
}
