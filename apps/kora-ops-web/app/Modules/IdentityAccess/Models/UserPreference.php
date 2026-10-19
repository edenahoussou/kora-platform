<?php

namespace App\Modules\IdentityAccess\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $push_notifications
 * @property bool $whatsapp_notifications
 * @property bool $sms_notifications
 * @property bool $email_notifications
 * @property string $locale
 * @property string $theme
 * @property string|null $timezone
 * @property bool $dispatch_alerts
 * @property bool $cashier_alerts
 * @property bool $security_alerts
 * @property User $user
 */
class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'push_notifications',
        'whatsapp_notifications',
        'sms_notifications',
        'email_notifications',
        'locale',
        'theme',
        'timezone',
        'dispatch_alerts',
        'cashier_alerts',
        'security_alerts',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'push_notifications' => 'boolean',
            'whatsapp_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'email_notifications' => 'boolean',
            'dispatch_alerts' => 'boolean',
            'cashier_alerts' => 'boolean',
            'security_alerts' => 'boolean',
        ];
    }
}
