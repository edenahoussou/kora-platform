<?php

namespace App\Modules\Departures\Models;

use App\Casts\UtcDateTime;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $driver_id
 * @property int|null $secondary_driver_id
 * @property int|null $conductor_id
 * @property int|null $mechanic_id
 * @property CarbonImmutable $service_date
 * @property CarbonImmutable $scheduled_at
 * @property CarbonImmutable $arrival_at
 * @property array<string, mixed> $snapshot
 */
class PlannedDeparture extends KoraModel
{
    protected $fillable = [
        'uuid',
        'programme_id',
        'version_id',
        'slot_key',
        'service_date',
        'scheduled_at',
        'arrival_at',
        'timezone',
        'line_id',
        'bus_id',
        'driver_id',
        'secondary_driver_id',
        'conductor_id',
        'mechanic_id',
        'departure_id',
        'state',
        'is_exception',
        'snapshot',
        'revision',
    ];

    protected function casts(): array
    {
        return ['service_date' => 'immutable_date', 'scheduled_at' => UtcDateTime::class, 'arrival_at' => UtcDateTime::class, 'is_exception' => 'boolean', 'snapshot' => 'array', 'revision' => 'integer'];
    }

    /** @return BelongsTo<DepartureProgramme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(DepartureProgramme::class, 'programme_id');
    }

    /** @return BelongsTo<DepartureTemplateVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DepartureTemplateVersion::class, 'version_id');
    }

    /** @return BelongsTo<Departure, $this> */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class, 'departure_id');
    }
}
