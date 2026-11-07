<?php

namespace App\Modules\KoraPoints\Models;

use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Support\Models\KoraModel;
use Database\Factories\KoraPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $agency_id
 * @property array<string, mixed>|null $settlement_calendar
 * @property int|null $ceiling_amount
 * @property int|null $commission_basis_points
 */
class KoraPoint extends KoraModel implements HasMedia
{
    /** @use HasFactory<KoraPointFactory> */
    use HasFactory, InteractsWithMedia;

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

    /** @return HasMany<KoraPointVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(KoraPointVisit::class, 'point_id');
    }

    /** @return HasMany<KoraPointPlvOrder, $this> */
    public function plvOrders(): HasMany
    {
        return $this->hasMany(KoraPointPlvOrder::class, 'point_id');
    }

    /** Declares the single-file point logo collection. */
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
