<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelTariff extends KoraModel
{
    protected $fillable = ['corridor_id', 'base_amount', 'step_amount', 'step_grams', 'maximum_grams', 'return_amount', 'created_by'];

    /** @return BelongsTo<ParcelCorridor, $this> */
    public function corridor(): BelongsTo
    {
        return $this->belongsTo(ParcelCorridor::class);
    }
}
