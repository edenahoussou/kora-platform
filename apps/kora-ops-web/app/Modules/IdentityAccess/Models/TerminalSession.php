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
 * @property int $user_id
 * @property int|null $personal_access_token_id
 * @property Carbon $started_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 * @property TerminalP501 $terminal
 * @property User $user
 */
class TerminalSession extends KoraModel
{
    protected $fillable = [
        'uuid',
        'terminal_p501_id',
        'user_id',
        'personal_access_token_id',
        'started_at',
        'last_seen_at',
        'revoked_at',
        'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'personal_access_token_id' => 'integer',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
