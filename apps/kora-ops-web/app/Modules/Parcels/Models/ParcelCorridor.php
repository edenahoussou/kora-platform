<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParcelCorridor extends KoraModel
{
    protected $fillable = ['origin_hub_id', 'destination_hub_id', 'enabled', 'wallet_cash_enabled'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'wallet_cash_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<ParcelHub, $this> */
    public function originHub(): BelongsTo
    {
        return $this->belongsTo(ParcelHub::class);
    }

    /** @return BelongsTo<ParcelHub, $this> */
    public function destinationHub(): BelongsTo
    {
        return $this->belongsTo(ParcelHub::class);
    }

    /** @return HasMany<ParcelTariff, $this> */
    public function tariffs(): HasMany
    {
        return $this->hasMany(ParcelTariff::class, 'corridor_id');
    }
}
