<?php

namespace App\Modules\IdentityAccess\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $terminal_p501_id
 * @property int|null $assigned_user_id
 * @property int|null $agency_id
 * @property int|null $assigned_by
 * @property Carbon $assigned_at
 * @property Carbon|null $unassigned_at
 * @property string|null $reason
 * @property TerminalP501 $terminal
 * @property User|null $assignedUser
 * @property Agency|null $agency
 * @property User|null $assignedByUser
 */
class TerminalAssignment extends KoraModel
{
    protected $fillable = [
        'uuid',
        'terminal_p501_id',
        'assigned_user_id',
        'agency_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TerminalP501, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TerminalP501::class, 'terminal_p501_id');
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
     * @return BelongsTo<User, $this>
     */
    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
