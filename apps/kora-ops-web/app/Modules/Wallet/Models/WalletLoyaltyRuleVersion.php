<?php

namespace App\Modules\Wallet\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Version de paramètres de fidélité KORA BUS.
 *
 * @property int $version
 * @property WalletLoyaltyRuleVersionStatus $status
 * @property array<string, mixed> $rules
 * @property Carbon|null $effective_at
 * @property Carbon|null $published_at
 * @property Carbon|null $activated_at
 */
class WalletLoyaltyRuleVersion extends KoraModel
{
    protected $fillable = [
        'version',
        'status',
        'rules',
        'created_by_user_id',
        'scheduled_by_user_id',
        'effective_at',
        'published_at',
        'activated_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => WalletLoyaltyRuleVersionStatus::class,
            'rules' => 'array',
            'effective_at' => 'datetime',
            'published_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }
}
