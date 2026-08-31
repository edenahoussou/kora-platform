<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\LineStatus;
use App\Modules\BusNetwork\Data\LineType;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<string, string>|null $countries_crossed
 * @property LineStatus $status
 * @property LineType $type
 * @property bool $bidirectional
 */
class Line extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'type',
        'bidirectional',
        'countries_crossed',
        'distance_km',
        'estimated_duration_minutes',
        'status',
    ];

    /**
     * @return HasMany<Stop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class)->orderBy('stop_order');
    }

    /**
     * @return HasMany<Departure, $this>
     */
    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    /**
     * @return HasMany<Fare, $this>
     */
    public function fares(): HasMany
    {
        return $this->hasMany(Fare::class);
    }

    protected function casts(): array
    {
        return [
            'countries_crossed' => 'array',
            'bidirectional' => 'boolean',
            'status' => LineStatus::class,
            'type' => LineType::class,
        ];
    }
}
