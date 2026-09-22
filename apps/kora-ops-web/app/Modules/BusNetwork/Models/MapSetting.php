<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\MapProvider;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Database\Eloquent\Model;

/**
 * Réglage admin du fournisseur cartographique. Table à ligne unique :
 * `map_settings` est conservée comme journal de compatibilité. La source de
 * vérité runtime est désormais IntegrationsSettings.
 *
 * @property MapProvider|string|null $provider
 * @property int|null $updated_by
 */
class MapSetting extends Model
{
    protected $fillable = [
        'provider',
        'updated_by',
    ];

    protected function casts(): array
    {
        return ['provider' => MapProvider::class];
    }

    public static function current(): ?self
    {
        return static::query()->first();
    }

    /**
     * Retourne le fournisseur casté en enum, ou null si absent/invalide.
     */
    public function providerEnum(): ?MapProvider
    {
        /** @var MapProvider|string|null $provider */
        $provider = $this->getAttribute('provider');

        if ($provider instanceof MapProvider) {
            return $provider;
        }

        if (is_string($provider) && $provider !== '') {
            return MapProvider::tryFrom($provider);
        }

        return null;
    }

    /**
     * Le fournisseur effectivement actif : le réglage admin s'il existe,
     * sinon la valeur d'environnement MAP_PROVIDER (openstreetmap par défaut).
     */
    public static function resolvedProvider(): MapProvider
    {
        try {
            return MapProvider::from(app(IntegrationsSettings::class)->mapProvider);
        } catch (\Throwable) {
            $legacy = static::current()?->providerEnum();

            return $legacy ?? MapProvider::from((string) (config('services.maps.default_provider') ?: 'openstreetmap'));
        }
    }
}
