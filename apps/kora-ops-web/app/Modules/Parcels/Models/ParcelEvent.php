<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $observed_at
 * @property array<string, mixed>|null $details
 */
class ParcelEvent extends KoraModel
{
    protected $fillable = ['parcel_id', 'actor_id', 'terminal_id', 'hub_id', 'assignment_id', 'kind', 'operation_uuid', 'channel', 'observed_at', 'details'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Parcel events are immutable.'));
        static::deleting(fn () => throw new \LogicException('Parcel events are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'observed_at' => 'immutable_datetime',
            'details' => 'array',
        ];
    }

    /** @return BelongsTo<ParcelAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ParcelAssignment::class);
    }
}
