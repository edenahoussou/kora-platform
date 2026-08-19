<?php

namespace App\Modules\DynamicPricing\Models;

use App\Modules\DynamicPricing\Data\ServiceClassStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceClass extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'sort_order',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
        'sort_order' => 0,
    ];

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
            'sort_order' => 'integer',
            'status' => ServiceClassStatus::class,
        ];
    }

    /**
     * @param  Builder<ServiceClass>  $query
     * @return Builder<ServiceClass>
     */
    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
