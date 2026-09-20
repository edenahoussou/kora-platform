<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Notifications\Data\OpsNotificationSeverity;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $dedupe_key
 * @property OpsNotificationType $type
 * @property OpsNotificationSeverity $severity
 * @property string $title
 * @property string $message
 * @property int|null $departure_id
 * @property int|null $terminal_p501_id
 * @property int|null $subject_user_id
 * @property array<string, mixed>|null $data
 * @property Carbon $occurred_at
 * @property Departure|null $departure
 * @property TerminalP501|null $terminal
 * @property User|null $subject
 */
class OpsNotificationEvent extends KoraModel
{
    protected $fillable = [
        'uuid',
        'dedupe_key',
        'type',
        'severity',
        'title',
        'message',
        'departure_id',
        'terminal_p501_id',
        'subject_user_id',
        'data',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => OpsNotificationType::class,
            'severity' => OpsNotificationSeverity::class,
            'data' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Departure, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
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
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /**
     * @return HasMany<OpsNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(OpsNotification::class, 'event_id');
    }

    /**
     * @return HasMany<OpsNotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(OpsNotificationDelivery::class, 'event_id');
    }
}
