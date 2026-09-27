<?php

namespace App\Modules\IdentityAccess\Models;

use App\Models\User;
use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $name
 * @property DevicePlatform $platform
 * @property string $device_fingerprint_hash
 * @property string|null $push_token
 * @property Carbon|null $trusted_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $revoked_at
 * @property UserDeviceStatus $status
 * @property User $user
 */
class UserDevice extends KoraModel
{
    protected $hidden = ['id', 'device_fingerprint_hash', 'push_token'];

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'platform',
        'device_fingerprint_hash',
        'push_token',
        'trusted_at',
        'last_seen_at',
        'revoked_at',
        'status',
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
            'platform' => DevicePlatform::class,
            'trusted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'status' => UserDeviceStatus::class,
        ];
    }
}
