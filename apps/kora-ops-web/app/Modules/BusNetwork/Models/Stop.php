<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stop extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'line_id',
        'name',
        'city',
        'country',
        'stop_order',
        'km_from_origin',
        'latitude',
        'longitude',
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
     * @return HasMany<Fare, $this>
     */
    public function originFares(): HasMany
    {
        return $this->hasMany(Fare::class, 'origin_stop_id');
    }

    /**
     * @return HasMany<Fare, $this>
     */
    public function destinationFares(): HasMany
    {
        return $this->hasMany(Fare::class, 'destination_stop_id');
    }

    protected function casts(): array
    {
        return ['status' => StopStatus::class];
    }
}
