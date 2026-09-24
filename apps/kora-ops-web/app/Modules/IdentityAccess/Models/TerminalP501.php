<?php

namespace App\Modules\IdentityAccess\Models;

use App\Models\User;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Data\TerminalType;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $serial_number
 * @property TerminalType $type
 * @property string|null $hardware_model
 * @property string|null $os_version
 * @property string|null $app_version
 * @property int|null $assigned_user_id
 * @property int|null $agency_id
 * @property TerminalP501Status $status
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $last_handshake_at
 * @property User|null $assignedUser
 * @property Agency|null $agency
 */
class TerminalP501 extends KoraModel
{
    use SoftDeletes;

    protected $table = 'terminals_p501';

    protected $fillable = [
        'uuid',
        'serial_number',
        'type',
        'hardware_model',
        'os_version',
        'app_version',
        'assigned_user_id',
        'agency_id',
        'status',
        'last_sync_at',
        'last_handshake_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TerminalType::class,
            'status' => TerminalP501Status::class,
            'last_sync_at' => 'datetime',
            'last_handshake_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return HasMany<TerminalAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TerminalAssignment::class, 'terminal_p501_id')->latest('assigned_at');
    }

    /**
     * @return HasMany<TerminalSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TerminalSession::class, 'terminal_p501_id');
    }

    /**
     * Un terminal ne peut ouvrir une session OPS Mobile ou effectuer une
     * opération (scan, sync, impression) que s'il est enregistré et actif.
     */
    public function isUsable(): bool
    {
        return $this->status === TerminalP501Status::Active;
    }
}
