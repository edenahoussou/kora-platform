<?php

namespace App\Modules\AppDistribution\Http\Requests;

use App\Modules\AppDistribution\Data\AppVersion;
use App\Modules\AppDistribution\Data\ReleaseEnvironment;
use App\Modules\AppDistribution\Data\ReleasePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vérification de mise à jour au lancement de l'app (avant toute session).
 *
 * Le canal store (`passenger_mobile`) cible une plateforme et un environnement
 * précis. L'environnement par défaut côté serveur est `production`.
 */
class AppUpdateCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['nullable', Rule::enum(ReleasePlatform::class)],
            'environment' => ['nullable', Rule::enum(ReleaseEnvironment::class)],
            'version' => ['nullable', 'string', 'max:64'],
            'build' => ['nullable', 'integer', 'min:0', 'max:2100000000'],
        ];
    }

    /**
     * `version` peut porter le build (`1.4.0+42`) ; un `build` explicite
     * prime sur celui éventuellement inclus dans `version`.
     */
    public function installedVersion(): ?AppVersion
    {
        $version = AppVersion::parse($this->string('version')->trim()->value());

        if ($version === null) {
            return null;
        }

        return $this->filled('build') ? $version->withBuild($this->integer('build')) : $version;
    }

    public function platform(): ?ReleasePlatform
    {
        $value = $this->string('platform')->trim()->value();

        return $value === '' ? null : ReleasePlatform::tryFrom($value);
    }

    public function environment(): ReleaseEnvironment
    {
        $value = $this->string('environment')->trim()->value();

        return $value === '' ? ReleaseEnvironment::Production : ReleaseEnvironment::from($value);
    }

    /** @return array<string, array{description: string, example?: mixed}> */
    public function queryParameters(): array
    {
        return [
            'platform' => ['description' => 'Plateforme cible (`android` ou `ios`), requise pour le canal store.', 'example' => 'android'],
            'environment' => ['description' => 'Environnement cible. Défaut serveur : `production`.', 'example' => 'production'],
            'version' => ['description' => 'Version installée (semver, build optionnel après « + »).', 'example' => '1.3.2+37'],
            'build' => ['description' => 'Build number installé (versionCode Android / build iOS).', 'example' => 37],
        ];
    }
}
