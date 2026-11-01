<?php

namespace App\Modules\KoraPoints\Models;

use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\KoraPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $agency_id
 * @property array<string, mixed>|null $settlement_calendar
 * @property int|null $ceiling_amount
 * @property int|null $commission_basis_points
 */
class KoraPoint extends KoraModel
{
    /** @use HasFactory<KoraPointFactory> */
    use HasFactory;

    /** Returns the bound model factory. */
    protected static function newFactory(): KoraPointFactory
    {
        return KoraPointFactory::new();
    }

    protected $attributes = ['status' => 'active'];

    protected $table = 'kora_points';

    protected $fillable = ['agency_id', 'organization_id', 'status', 'responsible_name', 'phone', 'address', 'latitude', 'longitude', 'commission_basis_points', 'ceiling_amount', 'settlement_calendar', 'uuid'];

    /** @return HasMany<KoraPointEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(KoraPointEntry::class, 'point_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['settlement_calendar' => 'array', 'commission_basis_points' => 'integer', 'ceiling_amount' => 'integer'];
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    /** @return BelongsTo<KoraPointOrganization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(KoraPointOrganization::class, 'organization_id');
    }

    /** @return HasMany<KoraPointSale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(KoraPointSale::class, 'point_id');
    }
}
