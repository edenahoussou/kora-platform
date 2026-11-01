<?php

namespace App\Modules\KoraPoints\Models;

use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_id
 * @property int|null $passenger_id
 * @property int $requested_by
 * @property int|null $amount
 * @property CarbonImmutable|null $executed_at
 * @property CarbonImmutable|null $confirmed_at
 */
class KoraPointRefund extends KoraModel
{
    protected $attributes = ['status' => 'requested'];

    protected $table = 'kora_point_refunds';

    protected $fillable = ['sale_id', 'passenger_id', 'payment_refund_id', 'requested_by', 'authorized_by', 'executed_by', 'confirmed_by', 'status', 'executor', 'amount', 'method', 'reference', 'beneficiary', 'reason', 'executed_at', 'confirmed_at', 'uuid'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'executed_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<KoraPointSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(KoraPointSale::class, 'sale_id');
    }

    /** @return BelongsTo<PaymentRefund, $this> */
    public function paymentRefund(): BelongsTo
    {
        return $this->belongsTo(PaymentRefund::class, 'payment_refund_id');
    }

    /** Forbids deleting a partner refund request. */
    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Une demande de remboursement partenaire ne peut pas être supprimée.'));
    }
}
