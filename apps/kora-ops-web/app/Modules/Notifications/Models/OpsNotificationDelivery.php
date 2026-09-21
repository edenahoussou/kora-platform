<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationDeliveryStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit de livraison : un événement, un destinataire, un canal, un résultat.
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $notification_id
 * @property int $recipient_user_id
 * @property OpsNotificationChannel $channel
 * @property OpsNotificationDeliveryStatus $status
 * @property string|null $mail_role
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $delivered_at
 * @property User $recipient
 */
class OpsNotificationDelivery extends KoraModel
{
    protected $fillable = [
        'uuid',
        'event_id',
        'notification_id',
        'recipient_user_id',
        'channel',
        'status',
        'mail_role',
        'attempts',
        'error',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => OpsNotificationChannel::class,
            'status' => OpsNotificationDeliveryStatus::class,
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
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
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
