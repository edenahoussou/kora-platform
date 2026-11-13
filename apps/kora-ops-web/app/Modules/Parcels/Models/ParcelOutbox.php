<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array{phone: string, message: string} $payload
 */
class ParcelOutbox extends KoraModel
{
    protected $fillable = ['parcel_id', 'key', 'kind', 'payload', 'due_at', 'completed_at', 'attempts'];

    protected $hidden = ['id', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Parcel, $this> */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }
}
