<?php

namespace App\Modules\Departures\Models;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Departure extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'line_id',
        'bus_id',
        'departure_date',
        'departure_time',
        'estimated_arrival_at',
        'driver_id',
        'conductor_id',
        'created_by',
        'status',
    ];

    /**
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * @return BelongsTo<Bus, $this>
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<ReservationSegment, $this>
     */
    public function reservationSegments(): HasMany
    {
        return $this->hasMany(ReservationSegment::class);
    }

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'estimated_arrival_at' => 'datetime',
            'status' => DepartureStatus::class,
        ];
    }
}
