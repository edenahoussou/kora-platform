<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Trace d'envoi d'un rappel d'expiration de points (J-30, J-7, J-1).
 *
 * @property int $days_before
 * @property Carbon $expires_on
 */
class WalletPointsExpiryReminder extends KoraModel
{
    protected $fillable = [
        'uuid',
        'wallet_id',
        'days_before',
        'expires_on',
        'sent_at',
    ];

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    protected function casts(): array
    {
        return [
            'days_before' => 'integer',
            'expires_on' => 'date',
            'sent_at' => 'datetime',
        ];
    }
}
