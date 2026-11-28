<?php

namespace App\Modules\AppDistribution\Http\Requests;

use App\Modules\AppDistribution\Data\AppVersion;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Data\ReleaseEnvironment;
use App\Modules\AppDistribution\Data\ReleasePlatform;
use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une release store pour l'application Passager (#342).
 *
 * Contrairement au canal APK Convoyeur, aucune binaire n'est téléversée :
 * la release référence le lien public officiel du store Android/iOS. Elle
 * reste invisible publiquement tant que la confirmation Ops et la fin de
 * rollout n'ont pas été enregistrées.
 */
class CreateStoreAppReleaseRequest extends FormRequest
{
    public const MAX_BUILD_NUMBER = 2_100_000_000;

    public function authorize(): bool
    {
        return $this->user()?->can('create', AppRelease::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'version' => is_string($this->input('version')) ? ltrim(trim($this->input('version')), 'vV') : $this->input('version'),
            'is_mandatory' => $this->boolean('is_mandatory'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::enum(ReleasePlatform::class)],
            'environment' => ['nullable', Rule::enum(ReleaseEnvironment::class)],
            'store_url' => ['required', 'url', 'max:2048'],
            'version' => ['required', 'string', 'max:64', 'regex:'.AppVersion::SEMVER_PATTERN],
            'build_number' => [
                'required',
                'integer',
                'min:1',
                'max:'.self::MAX_BUILD_NUMBER,
                Rule::unique('app_releases', 'build_number')
                    ->where('app', ReleasedApp::PassengerMobile->value)
                    ->where('channel', 'store')
                    ->where('platform', $this->string('platform')->value())
                    ->where('environment', $this->string('environment')->value() ?: ReleaseEnvironment::Production->value),
            ],
            'release_notes' => ['nullable', 'string', 'max:5000'],
            'is_mandatory' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_url.url' => 'Le lien du store doit être une adresse HTTP(S) valide.',
            'version.regex' => 'La version doit respecter le format semver MAJEUR.MINEUR.CORRECTIF (ex. 1.4.0).',
            'build_number.unique' => 'Ce build number est déjà utilisé pour cette plateforme et cet environnement.',
        ];
    }

    public function platform(): ReleasePlatform
    {
        return ReleasePlatform::from($this->string('platform')->value());
    }

    public function environment(): ReleaseEnvironment
    {
        $value = $this->string('environment')->value();

        return $value === '' ? ReleaseEnvironment::Production : ReleaseEnvironment::from($value);
    }

    /**
     * @return array{platform: ReleasePlatform, environment: ReleaseEnvironment, store_url: string, version: string, build_number: int, release_notes: string|null, is_mandatory: bool}
     */
    public function payload(): array
    {
        $notes = $this->string('release_notes')->trim()->value();

        return [
            'platform' => $this->platform(),
            'environment' => $this->environment(),
            'store_url' => $this->string('store_url')->trim()->value(),
            'version' => $this->string('version')->trim()->value(),
            'build_number' => $this->integer('build_number'),
            'release_notes' => $notes !== '' ? $notes : null,
            'is_mandatory' => $this->boolean('is_mandatory'),
        ];
    }
}
