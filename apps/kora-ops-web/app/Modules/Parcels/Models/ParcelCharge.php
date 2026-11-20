<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Payments\Models\Payment;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $superseded_at
 */
class ParcelCharge extends KoraModel
{
    protected $fillable = ['parcel_id', 'tariff_id', 'tax_rule_id', 'return_leg', 'total', 'net', 'tax', 'tax_code', 'tax_rate', 'fiscal_group', 'settled_payment_id', 'superseded_at'];

    protected function casts(): array
    {
        return [
            'return_leg' => 'boolean',
            'tax_rate' => 'decimal:4',
            'superseded_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ParcelTariff, $this> */
    public function tariff(): BelongsTo
    {
        return $this->belongsTo(ParcelTariff::class);
    }

    /** @return BelongsTo<Parcel, $this> */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function settledPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
