<?php

namespace App\Modules\KoraPoints\Models;

use App\Models\User;
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

    /** Returns the bound model factory. */
    protected static function newFactory(): KoraPointOrganizationFactory
    {
        return KoraPointOrganizationFactory::new();
    }

    protected $attributes = ['status' => 'active'];

    protected $table = 'kora_point_organizations';

    protected $fillable = ['code', 'name', 'status', 'phone', 'address', 'commission_basis_points', 'ceiling_amount', 'settlement_calendar', 'uuid'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['settlement_calendar' => 'array', 'commission_basis_points' => 'integer', 'ceiling_amount' => 'integer'];
    }

    /** @return HasMany<KoraPoint, $this> */
    public function points(): HasMany
    {
        return $this->hasMany(KoraPoint::class, 'organization_id');
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'kora_point_organization_id');
    }

    /** @return HasMany<KoraPartnerDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(KoraPartnerDocument::class, 'organization_id');
    }

    /** @return HasMany<KoraPointSale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(KoraPointSale::class, 'organization_id');
    }
}
