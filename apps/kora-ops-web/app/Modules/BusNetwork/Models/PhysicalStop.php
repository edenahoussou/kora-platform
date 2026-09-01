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
        'name',
        'city',
        'country',
        'latitude',
        'longitude',
        'status',
    ];

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
