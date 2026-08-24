<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\Payments\Models\Payment;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property ReservationStatus $status
 * @property ReservationChannel|null $channel
 * @property Carbon|null $expires_at
 * @property array<string, mixed>|null $total_snapshot
 */
class Reservation extends KoraModel
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'departure_id',
        'price_quote_id',
        'status',
        'total_amount',
        'currency',
        'channel',
        'expires_at',
        'total_snapshot',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Departure, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    /**
     * @return BelongsTo<PriceQuote, $this>
     */
    public function priceQuote(): BelongsTo
    {
        return $this->belongsTo(PriceQuote::class);
    }

    /**
     * @return HasMany<ReservationSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(ReservationSegment::class);
    }

    /**
     * @return HasMany<ReservationPassenger, $this>
     */
    public function passengers(): HasMany
    {
        return $this->hasMany(ReservationPassenger::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'channel' => ReservationChannel::class,
            'expires_at' => 'datetime',
            'total_snapshot' => 'array',
        ];
    }
}
