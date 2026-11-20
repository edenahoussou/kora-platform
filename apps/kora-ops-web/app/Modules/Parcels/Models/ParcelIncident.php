<?php

namespace App\Modules\Parcels\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $resolved_at
 * @property bool $blocking
 */
class ParcelIncident extends KoraModel
{
    protected $fillable = [
        'parcel_id',
        'kind',
        'description',
        'blocking',
        'opened_by_id',
        'idempotency_key',
        'resolved_at',
        'resolved_by_id',
        'resolution_reason',
    ];

    /** @return BelongsTo<Parcel, $this> */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    protected function casts(): array
    {
        return [
            'blocking' => 'boolean',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
