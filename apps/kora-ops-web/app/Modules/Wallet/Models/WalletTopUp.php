<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Payments\Models\Payment;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Wallet\Data\WalletTopUpStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recharge Wallet adossée au cycle de paiement provider existant.
 *
 * @property int $wallet_id
 * @property int $amount
 * @property WalletTopUpStatus $status
 */
class WalletTopUp extends KoraModel
{
    protected $table = 'wallet_topups';

    protected $fillable = [
        'uuid',
        'wallet_id',
        'payment_id',
        'amount',
        'status',
        'idempotency_key',
        'next_reconciliation_at',
        'reconciliation_attempts',
    ];

    /**
     * Wallet rechargé.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Paiement provider qui porte la recharge.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Casts du montant et du statut.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'next_reconciliation_at' => 'datetime',
            'reconciliation_attempts' => 'integer',
            'status' => WalletTopUpStatus::class,
        ];
    }
}
