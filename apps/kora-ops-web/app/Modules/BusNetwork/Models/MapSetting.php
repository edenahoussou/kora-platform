<?php

namespace App\Modules\BusNetwork\Models;

use App\Modules\BusNetwork\Data\MapProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Réglage admin du fournisseur cartographique. Table à ligne unique :
 * utiliser MapSetting::resolvedProvider() plutôt que Model::query()->first(),
 * pour centraliser le repli sur config('services.maps.default_provider')
 * quand aucun réglage admin n'a été enregistré.
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
        /** @var string $cached */
        $cached = Cache::remember(
            'map-settings:provider',
            now()->addMinutes(10),
            function (): string {
                $setting = static::current();

                $provider = $setting !== null
                    ? $setting->providerEnum()
                    : null;

                if (! $provider instanceof MapProvider) {
                    $default = (string) (config('services.maps.default_provider') ?: 'openstreetmap');

                    $provider = MapProvider::from($default);
                }

                return $provider->value;
            }
        );

        return MapProvider::from($cached);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('map-settings:provider'));
        static::deleted(fn () => Cache::forget('map-settings:provider'));
    }
}
