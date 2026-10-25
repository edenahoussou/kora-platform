<?php

namespace App\Modules\AppDistribution\Http\Requests;

use App\Modules\AppDistribution\Data\AppVersion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Vérification de mise à jour au lancement de l'app (avant toute session) :
 * l'appareil fournit sa version installée, le serveur répond avec le même
 * bloc `update` que handshake/sync/activity.
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

    /** @return array<string, array{description: string, example?: mixed}> */
    public function queryParameters(): array
    {
        return [
            'version' => ['description' => 'Version installée (semver, build optionnel après « + »).', 'example' => '1.3.2+37'],
            'build' => ['description' => 'Build number installé (versionCode Android).', 'example' => 37],
        ];
    }
}
