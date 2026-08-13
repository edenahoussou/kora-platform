<?php

namespace App\Modules\Booking\Models;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationSegment extends KoraModel
{
    protected $fillable = [
        'uuid',
        'reservation_id',
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
            'released_at' => 'datetime',
        ];
    }
}
