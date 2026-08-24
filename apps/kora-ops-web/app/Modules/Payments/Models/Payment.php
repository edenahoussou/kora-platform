<?php

namespace App\Modules\Payments\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $amount
 * @property PaymentStatus $status
 * @property Carbon|null $confirmed_at
 * @property array<string, mixed>|null $provider_payload
 */
class Payment extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'reservation_id',
        'provider',
        'provider_reference',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'confirmed_at',
        'provider_payload',
    ];

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'confirmed_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }
}
