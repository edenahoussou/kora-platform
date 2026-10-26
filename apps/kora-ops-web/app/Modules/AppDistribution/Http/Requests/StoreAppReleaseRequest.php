<?php

namespace App\Modules\AppDistribution\Http\Requests;

use App\Modules\AppDistribution\Data\AppVersion;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Models\AppRelease;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;
use ZipArchive;

/**
 * Publication d'un package (MEZ-150) : fichier APK + version semver +
 * build number + notes + flag obligatoire.
 *
 * Au-delà des règles de forme, la requête garantit la cohérence avec ce
 * qu'Android acceptera d'installer :
 *  - le fichier est bien une archive APK (signature ZIP + AndroidManifest.xml) ;
 *  - le build number (versionCode) est strictement supérieur à tous ceux
 *    déjà publiés pour l'application, archives comprises — un appareil
 *    passé par une version archivée refuserait sinon la mise à jour ;
 *  - la version semver ne recule pas par rapport à la plus haute déjà
 *    publiée (sinon l'ordre « semver puis build » deviendrait incohérent).
 */
class StoreAppReleaseRequest extends FormRequest
{
    /** Taille maximale d'un package : 200 Mo (vérifier aussi upload_max_filesize / post_max_size PHP). */
    public const MAX_PACKAGE_KILOBYTES = 204_800;

    /** Plafond Android du versionCode. */
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
            'app' => ['required', Rule::enum(ReleasedApp::class)],
            'package' => ['required', 'file', 'extensions:apk', 'max:'.self::MAX_PACKAGE_KILOBYTES],
            'version' => ['required', 'string', 'max:64', 'regex:'.AppVersion::SEMVER_PATTERN],
            'build_number' => [
                'required',
                'integer',
                'min:1',
                'max:'.self::MAX_BUILD_NUMBER,
                Rule::unique('app_releases', 'build_number')->where('app', $this->string('app')->value()),
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
            'package.extensions' => 'Le fichier doit être un package Android (.apk).',
            'package.max' => 'Le package ne doit pas dépasser :max Ko.',
            'version.regex' => 'La version doit respecter le format semver MAJEUR.MINEUR.CORRECTIF (ex. 1.4.0).',
            'build_number.unique' => 'Ce build number est déjà utilisé pour cette application.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'package' => 'package APK',
            'version' => 'version',
            'build_number' => 'build number',
            'release_notes' => 'notes de version',
            'is_mandatory' => 'mise à jour obligatoire',
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validatePackageContent($validator);
                $this->validateMonotonicVersion($validator);
            },
        ];
    }

    public function releasedApp(): ReleasedApp
    {
        return ReleasedApp::from($this->string('app')->value());
    }

    public function package(): UploadedFile
    {
        $file = $this->file('package');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('A validated release package is missing.');
        }

        return $file;
    }

    /**
     * @return array{version: string, build_number: int, release_notes: string|null, is_mandatory: bool}
     */
    public function payload(): array
    {
        $notes = $this->string('release_notes')->trim()->value();

        return [
            'version' => $this->string('version')->trim()->value(),
            'build_number' => $this->integer('build_number'),
            'release_notes' => $notes !== '' ? $notes : null,
            'is_mandatory' => $this->boolean('is_mandatory'),
        ];
    }

    private function validatePackageContent(Validator $validator): void
    {
        $path = $this->package()->getRealPath();

        if ($path === false) {
            $validator->errors()->add('package', 'Le package téléversé est illisible.');

            return;
        }

        $handle = fopen($path, 'rb');
        $signature = $handle !== false ? fread($handle, 4) : false;

        if ($handle !== false) {
            fclose($handle);
        }

        if ($signature !== "PK\x03\x04") {
            $validator->errors()->add('package', 'Le fichier n’est pas un package APK valide.');

            return;
        }

        if (! class_exists(ZipArchive::class)) {
            return;
        }

        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::RDONLY) !== true) {
            $validator->errors()->add('package', 'Le package APK est corrompu ou illisible.');

            return;
        }

        $hasManifest = $archive->locateName('AndroidManifest.xml') !== false;
        $archive->close();

        if (! $hasManifest) {
            $validator->errors()->add('package', 'Le fichier ne contient pas de manifeste Android : ce n’est pas un APK.');
        }
    }

    private function validateMonotonicVersion(Validator $validator): void
    {
        $app = $this->releasedApp();
        $candidate = AppVersion::fromParts($this->string('version')->value(), $this->integer('build_number'));

        if ($candidate === null) {
            return;
        }

        $existing = AppRelease::query()->forApp($app)->get(['version', 'build_number']);

        $highestBuild = $existing->max('build_number');

        if ($highestBuild !== null && $candidate->build !== null && $candidate->build <= $highestBuild) {
            $validator->errors()->add(
                'build_number',
                "Le build number doit être supérieur au dernier publié ({$highestBuild}) : Android refuse d’installer un build inférieur ou égal.",
            );
        }

        $highestVersion = $existing
            ->map(fn (AppRelease $release): ?AppVersion => AppVersion::parse($release->version))
            ->filter()
            ->sort(fn (AppVersion $left, AppVersion $right): int => $right->compare($left))
            ->first();

        if ($highestVersion !== null && $candidate->withBuild(null)->isOlderThan($highestVersion)) {
            $validator->errors()->add(
                'version',
                "La version doit être supérieure ou égale à la plus récente déjà publiée ({$highestVersion->semver()}).",
            );
        }
    }

    /** @return array<string, array{description: string, example?: mixed}> */
    public function bodyParameters(): array
    {
        return [
            'app' => ['description' => 'Application concernée.', 'example' => 'ops_mobile'],
            'package' => ['description' => 'Package Android (.apk).'],
            'version' => ['description' => 'Version semver.', 'example' => '1.4.0'],
            'build_number' => ['description' => 'Build number (versionCode Android).', 'example' => 42],
            'release_notes' => ['description' => 'Notes de version affichées aux utilisateurs.'],
            'is_mandatory' => ['description' => 'Mise à jour obligatoire pour toute version antérieure.', 'example' => false],
        ];
    }
}
