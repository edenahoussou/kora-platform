<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalStop extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'city',
        'country',
        'latitude',
        'longitude',
        'status',
    ];

    /** Stop label columns are a copy of the physical stop: propagate every change. */
    protected static function booted(): void
    {
        static::updated(function (PhysicalStop $physicalStop): void {
            if ($physicalStop->wasChanged(['name', 'city', 'country', 'latitude', 'longitude'])) {
                $physicalStop->stops()->update(self::canonicalStopAttributes($physicalStop));
            }
        });
    }

    /**
     * @return array{name: string, city: string, country: string, latitude: float|null, longitude: float|null}
     */
    public static function canonicalStopAttributes(PhysicalStop $physicalStop): array
    {
        return [
            'name' => (string) $physicalStop->name,
            'city' => (string) $physicalStop->city,
            'country' => (string) $physicalStop->country,
            'latitude' => $physicalStop->latitude !== null ? (float) $physicalStop->latitude : null,
            'longitude' => $physicalStop->longitude !== null ? (float) $physicalStop->longitude : null,
        ];
    }

    /**
     * @return HasMany<Stop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class);
    }

    protected function casts(): array
    {
        return ['status' => StopStatus::class];
    }
}
