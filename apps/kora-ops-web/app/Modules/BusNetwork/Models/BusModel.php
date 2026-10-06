<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\BusModelStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusModel extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'brand',
        'name',
        'default_seat_count',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return HasMany<Bus, $this>
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }

    protected function casts(): array
    {
        return [
            'status' => BusModelStatus::class,
        ];
    }

    /**
     * @param  Builder<BusModel>  $query
     * @return Builder<BusModel>
     */
    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('brand')->orderBy('name');
    }
}
