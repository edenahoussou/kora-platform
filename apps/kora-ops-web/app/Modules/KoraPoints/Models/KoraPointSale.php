<?php

namespace App\Modules\KoraPoints\Models;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Models\Payment;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $point_id
 * @property int|null $organization_id
 * @property int $reservation_id
 * @property int $payment_id
 * @property int $seller_id
 * @property int $gross_amount
 * @property int $commission_amount
 * @property int $net_amount
 * @property int $outstanding_amount
 * @property int $refunded_amount
 * @property int $commission_reversed
 * @property array<string, mixed> $terms_snapshot
 * @property CarbonImmutable $paid_at
 * @property CarbonImmutable $due_at
 * @property CarbonImmutable|null $settled_at Marqueur « a été réglée au moins une fois » — sert au choix de l'exécuteur des remboursements ; conservé quand la dette se rouvre.
 * @property string $status open = encours non soldé, settled = soldée, reopened = dette rouverte après règlement.
 */
class KoraPointSale extends KoraModel
{
    protected $attributes = ['status' => 'open', 'refunded_amount' => 0, 'commission_reversed' => 0];

    protected $table = 'kora_point_sales';

    protected $fillable = ['point_id', 'organization_id', 'reservation_id', 'payment_id', 'seller_id', 'status', 'gross_amount', 'commission_amount', 'net_amount', 'outstanding_amount', 'refunded_amount', 'commission_reversed', 'terms_snapshot', 'paid_at', 'due_at', 'settled_at', 'uuid'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['terms_snapshot' => 'array', 'gross_amount' => 'integer', 'commission_amount' => 'integer', 'net_amount' => 'integer', 'outstanding_amount' => 'integer', 'refunded_amount' => 'integer', 'commission_reversed' => 'integer', 'paid_at' => 'immutable_datetime', 'due_at' => 'immutable_datetime', 'settled_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<KoraPoint, $this> */
    public function point(): BelongsTo
    {
        return $this->belongsTo(KoraPoint::class, 'point_id');
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /** @return HasMany<KoraPointEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(KoraPointEntry::class, 'sale_id');
    }

    /** @return HasMany<KoraPointRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(KoraPointRefund::class, 'sale_id');
    }

    /** @return HasMany<KoraPointRemittanceLine, $this> */
    public function remittanceLines(): HasMany
    {
        return $this->hasMany(KoraPointRemittanceLine::class, 'sale_id');
    }

    /** Freezes sale terms after creation and forbids deletion. */
    protected static function booted(): void
    {
        static::updating(function (self $sale): void {
            if ($sale->isDirty(['point_id', 'organization_id', 'reservation_id', 'payment_id', 'seller_id', 'gross_amount', 'commission_amount', 'net_amount', 'terms_snapshot', 'paid_at'])) {
                throw new \LogicException('Les conditions de vente sont immuables.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Une vente partenaire ne peut pas être supprimée.'));
    }
}
