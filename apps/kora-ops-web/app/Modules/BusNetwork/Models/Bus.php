<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\BusStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bus extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'registration_number',
        'bus_model_id',
        'composition_id',
        'agency_id',
        'brand',
        'model',
        'seat_count',
        'status',
    ];

    /**
     * @return BelongsTo<BusModel, $this>
     */
    public function busModel(): BelongsTo
    {
        return $this->belongsTo(BusModel::class);
    }

    /**
     * @return BelongsTo<BusComposition, $this>
     */
    public function composition(): BelongsTo
    {
        return $this->belongsTo(BusComposition::class, 'composition_id');
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return HasMany<Seat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class)->orderByRaw('LENGTH(code), code');
    }

    /**
     * @return HasMany<Departure, $this>
     */
    public function departures(): HasMany
    {
        return $this->hasMany(Departure::class);
    }

    /**
     * @return BelongsToMany<BusAmenity, $this>
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(BusAmenity::class, 'bus_bus_amenity');
    }

    protected function casts(): array
    {
        return ['status' => BusStatus::class];
    }
}
