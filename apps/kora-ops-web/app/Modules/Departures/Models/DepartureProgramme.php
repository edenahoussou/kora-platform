<?php

namespace App\Modules\Departures\Models;

use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property list<array{from: string, version_id: int}> $version_schedule
 * @property list<array<string, mixed>> $exceptions
 */
class DepartureProgramme extends KoraModel
{
    protected $fillable = [
        'uuid',
        'name',
        'version_id',
        'starts_on',
        'ends_on',
        'automatic',
        'horizon_days',
        'revision',
        'exceptions',
        'version_schedule',
        'created_by',
        'automatic_approved_by',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'automatic' => 'boolean', 'horizon_days' => 'integer', 'revision' => 'integer', 'version_schedule' => 'array', 'exceptions' => 'array'];
    }

    /** @return BelongsTo<DepartureTemplateVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DepartureTemplateVersion::class, 'version_id');
    }

    /** @return HasMany<PlannedDeparture, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(PlannedDeparture::class, 'programme_id');
    }

    /** @return HasMany<ProgrammingRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ProgrammingRun::class, 'programme_id');
    }
}
