<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\BusAmenityStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusAmenity extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'label',
        'icon',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return BelongsToMany<Bus, $this>
     */
    public function buses(): BelongsToMany
    {
        return $this->belongsToMany(Bus::class, 'bus_bus_amenity');
    }

    protected function casts(): array
    {
        return [
            'status' => BusAmenityStatus::class,
        ];
    }

    /**
     * @param  Builder<BusAmenity>  $query
     * @return Builder<BusAmenity>
     */
    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('label');
    }
}
