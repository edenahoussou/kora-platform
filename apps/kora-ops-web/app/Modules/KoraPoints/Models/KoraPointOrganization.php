<?php

namespace App\Modules\KoraPoints\Models;

use App\Modules\Support\Models\KoraModel;
use Database\Factories\KoraPointOrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property array<string, mixed>|null $settlement_calendar
 * @property int|null $ceiling_amount
 * @property int|null $commission_basis_points
 */
class KoraPointOrganization extends KoraModel
{
    /** @use HasFactory<KoraPointOrganizationFactory> */
    use HasFactory;

    protected static function newFactory(): KoraPointOrganizationFactory
    {
        return KoraPointOrganizationFactory::new();
    }

    protected $attributes = ['status' => 'active'];

    protected $table = 'kora_point_organizations';

    protected $fillable = ['code', 'name', 'status', 'phone', 'address', 'commission_basis_points', 'ceiling_amount', 'settlement_calendar', 'uuid'];

    protected function casts(): array
    {
        return ['settlement_calendar' => 'array', 'commission_basis_points' => 'integer', 'ceiling_amount' => 'integer'];
    }

    /** @return HasMany<KoraPoint, $this> */
    public function points(): HasMany
    {
        return $this->hasMany(KoraPoint::class, 'organization_id');
    }
}
