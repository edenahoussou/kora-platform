<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusComposition extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'seat_count',
        'layout',
        'status',
    ];

    protected function casts(): array
    {
        return ['layout' => 'array'];
    }

    /**
     * @return HasMany<Bus, $this>
     */
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class, 'composition_id');
    }
}
