<?php

namespace App\Modules\AssistedBooking\Models;

use App\Models\User;
use App\Modules\AssistedBooking\Data\AssistedBookingChannel;
use App\Modules\AssistedBooking\Data\AssistedBookingStatus;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Models\Reservation;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\AssistedBookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property AssistedBookingStatus $status
 * @property AssistedBookingChannel $handoff_channel
 * @property ReservationChannel $origin_channel
 * @property array<string, mixed> $snapshot
 * @property Carbon $expires_at
 */
class AssistedBooking extends KoraModel
{
    /** @use HasFactory<AssistedBookingFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'reservation_id',
        'preferred_agency_id',
        'assigned_user_id',
        'origin_channel',
        'handoff_channel',
        'status',
        'contact_name',
        'contact_phone',
        'snapshot',
        'original_total_amount',
        'current_total_amount',
        'currency',
        'idempotency_key',
        'resume_idempotency_key',
        'reason_code',
        'reason_note',
        'expires_at',
        'claimed_at',
        'released_at',
        'refused_at',
        'unavailable_at',
        'cancelled_at',
        'finalized_at',
    ];

    protected static function newFactory(): AssistedBookingFactory
    {
        return AssistedBookingFactory::new();
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<Agency, $this> */
    public function handlingAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'preferred_agency_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    protected function casts(): array
    {
        return [
            'origin_channel' => ReservationChannel::class,
            'handoff_channel' => AssistedBookingChannel::class,
            'status' => AssistedBookingStatus::class,
            'snapshot' => 'array',
            'original_total_amount' => 'integer',
            'current_total_amount' => 'integer',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'released_at' => 'datetime',
            'refused_at' => 'datetime',
            'unavailable_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }
}
