<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\BusStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bus extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'registration_number',
        'brand',
        'model',
        'seat_count',
        'status',
    ];

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

    protected function casts(): array
    {
        return ['status' => BusStatus::class];
    }
}
