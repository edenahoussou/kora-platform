<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\Support\Models\KoraModel;
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

    /**
     * @return HasMany<Bus, $this>
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class);
    }
}
