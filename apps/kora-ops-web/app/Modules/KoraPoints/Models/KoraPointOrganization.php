<?php

namespace App\Modules\KoraPoints\Models;

use App\Models\User;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\KoraPointOrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property array<string, mixed>|null $settlement_calendar
 * @property int|null $ceiling_amount
 * @property int|null $commission_basis_points
 * @property string $country
 * @property string|null $registration_identifier
 * @property string|null $tax_identifier
 */
class KoraPointOrganization extends KoraModel implements HasMedia
{
    /** @use HasFactory<KoraPointOrganizationFactory> */
    use HasFactory, InteractsWithMedia;

    /** Returns the bound model factory. */
    protected static function newFactory(): KoraPointOrganizationFactory
    {
        return KoraPointOrganizationFactory::new();
    }

    protected $attributes = ['status' => 'active'];

    protected $table = 'kora_point_organizations';

    protected $fillable = ['code', 'name', 'status', 'country', 'registration_identifier', 'tax_identifier', 'phone', 'address', 'commission_basis_points', 'ceiling_amount', 'settlement_calendar', 'uuid'];

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

    /** Declares the single-file partner logo collection. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    /** Generates a contained thumbnail for list rendering. */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 200, 200)
            ->nonQueued(); // @phpstan-ignore-line — nonQueued() est défini sur Conversion, pas sur le retour de fit() selon PHPStan
    }
}
