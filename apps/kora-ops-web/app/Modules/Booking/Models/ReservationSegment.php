<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Notifications\Observers\ReservationSegmentManifestObserver;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ReservationSegmentStatus $status
 * @property Carbon|null $released_at
 * @property int|null $passenger_index
 */
#[ObservedBy([ReservationSegmentManifestObserver::class])]
class ReservationSegment extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'reservation_id',
        'leg_order',
        'direction',
        'passenger_index',
        'reservation_passenger_id',
        'departure_id',
        'seat_id',
        'origin_stop_id',
        'destination_stop_id',
        'origin_stop_order',
        'destination_stop_order',
        'status',
        'actual_dropoff_stop_id',
        'released_at',
    ];

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<ReservationPassenger, $this>
     */
    public function reservationPassenger(): BelongsTo
    {
        return $this->belongsTo(ReservationPassenger::class, 'reservation_passenger_id');
    }

    /**
     * @return BelongsTo<Departure, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    /**
     * @return BelongsTo<Seat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /**
     * @return BelongsTo<Stop, $this>
     */
    public function originStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'origin_stop_id');
    }

    /**
     * @return BelongsTo<Stop, $this>
     */
    public function destinationStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'destination_stop_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ReservationSegmentStatus::class,
            'leg_order' => 'integer',
            'passenger_index' => 'integer',
            'released_at' => 'datetime',
        ];
    }
}
