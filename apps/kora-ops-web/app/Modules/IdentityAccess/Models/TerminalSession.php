<?php

namespace App\Modules\IdentityAccess\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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
 * @property Carbon|null $expires_at
 * @property Carbon|null $absolute_expires_at
 * @property Carbon|null $renewed_at
 * @property int $renewal_count
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
        'expires_at',
        'absolute_expires_at',
        'renewed_at',
        'renewal_count',
        'revoked_at',
        'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
            'absolute_expires_at' => 'datetime',
            'renewed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'personal_access_token_id' => 'integer',
            'renewal_count' => 'integer',
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

    /**
     * Sessions non révoquées (elles peuvent être expirées : voir isExpired()).
     *
     * @param  Builder<TerminalSession>  $query
     * @return Builder<TerminalSession>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Expirée si la fenêtre courante est échue, si la durée absolue est
     * atteinte, ou si la session n'a jamais reçu d'échéance (antérieure à
     * MEZ-95 : jamais prolongée silencieusement).
     */
    public function isExpired(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if ($this->expires_at === null || $this->expires_at->lte($at)) {
            return true;
        }

        return $this->absolute_expires_at !== null && $this->absolute_expires_at->lte($at);
    }
}
