<?php

namespace App\Modules\Payments\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Payments\Data\PaymentRefundDestination;
use App\Modules\Payments\Data\PaymentRefundStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PaymentRefundStatus $status
 * @property PaymentRefundDestination $destination
 * @property array<string, mixed>|null $metadata
 */
class PaymentRefund extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'reservation_id',
        'reservation_passenger_id',
        'payment_id',
        'amount',
        'currency',
        'destination',
        'status',
        'reference',
        'idempotency_key',
        'metadata',
        'completed_at',
    ];

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<ReservationPassenger, $this> */
    public function reservationPassenger(): BelongsTo
    {
        return $this->belongsTo(ReservationPassenger::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'destination' => PaymentRefundDestination::class,
            'status' => PaymentRefundStatus::class,
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
