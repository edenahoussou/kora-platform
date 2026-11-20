<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array{phone: string, message: string} $payload
 * @property CarbonImmutable $due_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $claimed_at
 */
class ParcelOutbox extends KoraModel
{
    protected $fillable = [
        'parcel_id', 'key', 'kind', 'payload', 'due_at', 'completed_at', 'attempts',
        'claimed_at', 'claim_token', 'last_error_code', 'delivered_channel', 'provider_message_id',
    ];

    protected $hidden = ['id', 'payload', 'claim_token'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Parcel, $this> */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }
}
