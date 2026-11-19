<?php

namespace App\Modules\Parcels\Models;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\Support\Models\KoraModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $archived_at
 */
class ParcelHub extends KoraModel
{
    public const KIND_AGENCY = 'agency';

    public const KIND_PARTNER_POINT = 'partner_point';

    protected $fillable = ['agency_id', 'kora_point_id', 'kind', 'stop_id', 'enabled', 'supports_weighing', 'archived_at'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'supports_weighing' => 'boolean',
            'archived_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<KoraPoint, $this> */
    public function koraPoint(): BelongsTo
    {
        return $this->belongsTo(KoraPoint::class);
    }

    /** @return BelongsTo<Stop, $this> */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }

    /**
     * @param  Builder<ParcelHub>  $query
     * @return Builder<ParcelHub>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true)->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isActive(): bool
    {
        return $this->enabled && ! $this->isArchived();
    }
}
