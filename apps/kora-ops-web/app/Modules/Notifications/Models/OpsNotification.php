<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Notification d'un destinataire (état lu / non lu).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $event_id
 * @property int $user_id
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property OpsNotificationEvent|null $event
 * @property User $user
 */
class OpsNotification extends DatabaseNotification
{
    protected $table = 'notifications';

    protected $fillable = [
        'id',
        'type',
        'data',
        'notifiable_type',
        'notifiable_id',
        'audience',
        'event_id',
        'user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
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

    public function getUuidAttribute(): string
    {
        return (string) $this->getKey();
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
