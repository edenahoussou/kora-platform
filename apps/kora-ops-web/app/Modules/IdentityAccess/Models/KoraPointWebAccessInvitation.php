<?php

namespace App\Modules\IdentityAccess\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\Modules\IdentityAccess\Models\KoraPointWebAccessInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KoraPointWebAccessInvitation extends KoraModel
{
    /** @use HasFactory<KoraPointWebAccessInvitationFactory> */
    use HasFactory;

    public const string STATUS_PENDING_DELIVERY = 'pending_delivery';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_EXPIRED = 'expired';

    public const string STATUS_USED = 'used';

    public const string STATUS_REPLACED = 'replaced';

    protected $table = 'kora_point_web_access_invitations';

    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'delivery_channel',
        'sent_at',
        'expires_at',
        'used_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
        ];
    }
}
