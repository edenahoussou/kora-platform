<?php

namespace App\Modules\AppDistribution\Actions;

use App\Models\User;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function activity;

/**
 * Publie un nouveau package (APK) d'une application distribuée hors store.
 *
 * - Le sha256 et la taille sont calculés ICI, côté serveur, sur le fichier
 *   reçu — jamais fournis par le client. C'est ce sha256 que les appareils
 *   vérifient avant d'installer (MEZ-150).
 * - Le binaire est rangé sur le disque privé `releases`
 *   (`{app}/{uuid}.apk`) ; si l'insertion en base échoue, le fichier est
 *   supprimé pour ne laisser aucun orphelin.
 * - La release est publiée immédiatement (`published_at = now`) et devient
 *   la « dernière version » si elle est la plus récente.
 */
class PublishAppReleaseAction
{
    /**
     * @param  array{version: string, build_number: int, release_notes: string|null, is_mandatory: bool}  $attributes
     */
    public function execute(ReleasedApp $app, UploadedFile $artifact, array $attributes, User $actor): AppRelease
    {
        $sourcePath = $artifact->getRealPath();

        if ($sourcePath === false || ! is_readable($sourcePath)) {
            throw new RuntimeException('Le package téléversé est illisible.');
        }

        $sha256 = hash_file('sha256', $sourcePath);
        $size = filesize($sourcePath);

        if ($sha256 === false || $size === false) {
            throw new RuntimeException('Impossible de calculer l’empreinte du package téléversé.');
        }

        $uuid = (string) Str::uuid();
        $directory = $app->value;
        $filename = $uuid.'.apk';
        $path = $directory.'/'.$filename;

        Storage::disk(AppRelease::DISK)->putFileAs($directory, $artifact, $filename);

        try {
            $release = DB::transaction(function () use ($app, $artifact, $attributes, $actor, $uuid, $path, $sha256, $size): AppRelease {
                $release = AppRelease::query()->create([
                    'uuid' => $uuid,
                    'app' => $app,
                    'version' => $attributes['version'],
                    'build_number' => $attributes['build_number'],
                    'artifact_path' => $path,
                    'original_filename' => Str::limit($artifact->getClientOriginalName(), 250, ''),
                    'artifact_size' => $size,
                    'sha256' => $sha256,
                    'release_notes' => $attributes['release_notes'],
                    'is_mandatory' => $attributes['is_mandatory'],
                    'published_at' => now(),
                    'created_by' => $actor->id,
                ]);

                activity()
                    ->performedOn($release)
                    ->causedBy($actor)
                    ->withProperties([
                        'app' => $app->value,
                        'version' => $release->version,
                        'build_number' => $release->build_number,
                        'is_mandatory' => $release->is_mandatory,
                        'sha256' => $sha256,
                        'size' => $size,
                    ])
                    ->log('app_release_published');

                return $release;
            });
        } catch (Throwable $exception) {
            Storage::disk(AppRelease::DISK)->delete($path);

            throw $exception;
        }

        return $release->refresh();
    }
}
