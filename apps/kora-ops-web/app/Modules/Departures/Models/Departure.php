<?php

namespace App\Modules\Departures\Models;

use App\Casts\UtcDateTime;
use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property CarbonImmutable|null $scheduled_at
 * @property array<int, array<string, mixed>>|null $stop_times
 * @property Carbon|null $departure_date
 * @property Carbon|null $estimated_arrival_at
 * @property DepartureStatus $status
 * @property int $delay_minutes
 * @property string|null $delay_reason
 * @property CarbonImmutable|null $delay_recorded_at
 */
class Departure extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'scheduled_at', 'departure_timezone', 'stop_times', 'secondary_driver_id', 'mechanic_id',
        'line_id',
        'bus_id',
        'agency_id',
        'departure_date',
        'departure_time',
        'delay_minutes',
        'delay_reason',
        'delay_recorded_at',
        'delay_recorded_by',
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
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conductor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function delayRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delay_recorded_by');
    }

    /** @return HasOne<PlannedDeparture, $this> */
    public function plannedOccurrence(): HasOne
    {
        return $this->hasOne(PlannedDeparture::class, 'departure_id');
    }

    public function scheduledDepartureAt(): CarbonImmutable
    {
        if ($this->scheduled_at !== null) {
            return CarbonImmutable::instance($this->scheduled_at);
        }

        return CarbonImmutable::parse(
            $this->departure_date?->toDateString().' '.substr((string) $this->departure_time, 0, 8),
            config('app.timezone'),
        );
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
            'scheduled_at' => UtcDateTime::class,
            'stop_times' => 'array',
            'departure_date' => 'date',
            'estimated_arrival_at' => 'datetime',
            'delay_recorded_at' => 'datetime',
            'delay_minutes' => 'integer',
            'status' => DepartureStatus::class,
        ];
    }
}
