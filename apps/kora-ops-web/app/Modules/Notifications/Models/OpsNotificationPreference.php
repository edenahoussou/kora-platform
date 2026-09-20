<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property bool $in_app
 * @property bool $mail
 */
class OpsNotificationPreference extends KoraModel
{
    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'in_app',
        'mail',
    ];

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'mail' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
