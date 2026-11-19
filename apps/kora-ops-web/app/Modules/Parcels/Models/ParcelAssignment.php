<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Departures\Models\Departure;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $transported_at
 * @property CarbonImmutable|null $loaded_at
 * @property CarbonImmutable|null $unloaded_at
 * @property CarbonImmutable|null $released_at
 */
class ParcelAssignment extends KoraModel
{
    protected $fillable = ['parcel_id', 'departure_id', 'bus_id', 'return_leg', 'loaded_at', 'transported_at', 'unloaded_at', 'released_at'];

    protected function casts(): array
    {
        return [
            'return_leg' => 'boolean',
            'loaded_at' => 'immutable_datetime',
            'transported_at' => 'immutable_datetime',
            'unloaded_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Parcel, $this> */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    /** @return BelongsTo<Departure, $this> */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }
}
