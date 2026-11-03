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

    /**
     * Wallet réservé.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Réservation à l'origine du hold.
     *
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Paiement à l'origine du hold.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Indique si le hold est actif mais hors délai.
     *
     * @return bool Vrai si le hold doit être libéré
     */
    public function isExpired(): bool
    {
        return $this->status === WalletHoldStatus::Active && $this->expires_at->isPast();
    }

    /**
     * Casts des montants et du statut.
     *
     * @return array<string, string>
     */
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
