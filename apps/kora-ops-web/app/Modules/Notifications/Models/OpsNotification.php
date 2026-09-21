<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notification d'un destinataire (état lu / non lu).
 *
 * @property int $id
 * @property string $uuid
 * @property int $event_id
 * @property int $user_id
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property OpsNotificationEvent $event
 * @property User $user
 */
class OpsNotification extends KoraModel
{
    protected $fillable = [
        'uuid',
        'event_id',
        'user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OpsNotificationEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(OpsNotificationEvent::class, 'event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<OpsNotification>  $query
     * @return Builder<OpsNotification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
