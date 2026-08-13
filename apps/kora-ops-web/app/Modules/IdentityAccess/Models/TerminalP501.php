<?php

namespace App\Modules\IdentityAccess\Models;

use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class TerminalP501 extends KoraModel
{
    use SoftDeletes;

    protected $table = 'terminals_p501';

    protected $fillable = [
        'uuid',
        'serial_number',
        'assigned_user_id',
        'agency_id',
        'status',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TerminalP501Status::class,
            'last_sync_at' => 'datetime',
        ];
    }
}
