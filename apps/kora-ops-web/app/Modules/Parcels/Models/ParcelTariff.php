<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelTariff extends KoraModel
{
    protected $fillable = ['corridor_id', 'deposit_kind', 'version', 'base_amount', 'step_amount', 'step_grams', 'maximum_grams', 'return_amount', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (ParcelTariff $tariff): void {
            if (blank($tariff->deposit_kind)) {
                $tariff->deposit_kind = (string) ParcelCorridor::query()
                    ->with('originHub')
                    ->whereKey($tariff->corridor_id)
                    ->first()?->originHub?->kind ?: ParcelHub::KIND_AGENCY;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<ParcelCorridor, $this> */
    public function corridor(): BelongsTo
    {
        return $this->belongsTo(ParcelCorridor::class);
    }
}
