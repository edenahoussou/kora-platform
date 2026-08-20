<?php

namespace App\Modules\IdentityAccess\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string|null $nickname
 * @property string|null $name
 * @property string|null $email
 * @property string|null $token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property array<string, mixed>|null $raw_attributes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User $user
 */
class UserSocialAccount extends KoraModel
{
    protected $fillable = [
        'uuid',
        'user_id',
        'provider',
        'provider_user_id',
        'nickname',
        'name',
        'email',
        'token',
        'refresh_token',
        'token_expires_at',
        'raw_attributes',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'raw_attributes' => 'array',
        ];
    }
}
