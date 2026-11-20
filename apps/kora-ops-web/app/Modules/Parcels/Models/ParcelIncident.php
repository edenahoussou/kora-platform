<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelIncident extends KoraModel
{
    protected $fillable = ['parcel_id', 'kind', 'description', 'blocking', 'resolved_at'];

    /** @return BelongsTo<Parcel, $this> */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    protected function casts(): array
    {
        return [
            'blocking' => 'boolean',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
