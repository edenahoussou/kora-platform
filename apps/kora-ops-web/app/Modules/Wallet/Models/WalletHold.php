<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Models\Payment;
use App\Modules\Support\Models\KoraModel;
use App\Modules\Wallet\Data\WalletHoldStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Réservation de fonds (hold → capture → release) sur un Wallet.
 *
 * @property int $wallet_id
 * @property int $cash_amount
 * @property int $points_amount
 * @property WalletHoldStatus $status
 * @property Carbon $expires_at
 */
class WalletHold extends KoraModel
{
    protected $fillable = [
        'uuid',
        'wallet_id',
        'cash_amount',
        'points_amount',
        'reservation_id',
        'payment_id',
        'status',
        'expires_at',
        'idempotency_key',
    ];

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isExpired(): bool
    {
        return $this->status === WalletHoldStatus::Active && $this->expires_at->isPast();
    }

    protected function casts(): array
    {
        return [
            'cash_amount' => 'integer',
            'points_amount' => 'integer',
            'status' => WalletHoldStatus::class,
            'expires_at' => 'datetime',
        ];
    }
}
