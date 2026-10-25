<?php

namespace App\Modules\AppDistribution\Actions;

use App\Models\User;
use App\Modules\AppDistribution\Models\AppRelease;

use function activity;

/**
 * Modifie les métadonnées éditables d'une release : notes de version et
 * flag « mise à jour obligatoire ». Le binaire, la version et le build sont
 * immuables (le sha256 annoncé aux appareils ne doit jamais changer) : pour
 * corriger un package, on publie une nouvelle release.
 */
class UpdateAppReleaseAction
{
    /**
     * @param  array{release_notes: string|null, is_mandatory: bool}  $attributes
     */
    public function execute(AppRelease $release, array $attributes, User $actor): AppRelease
    {
        $release->fill([
            'release_notes' => $attributes['release_notes'],
            'is_mandatory' => $attributes['is_mandatory'],
        ]);

        $changes = $release->getDirty();

        if ($changes === []) {
            return $release;
        }

        $changedKeys = array_keys($changes);
        $original = collect($changedKeys)
            ->mapWithKeys(fn (string $key): array => [$key => $release->getOriginal($key)])
            ->all();

        $release->save();

        activity()
            ->performedOn($release)
            ->causedBy($actor)
            ->withProperties([
                'app' => $release->app->value,
                'version' => $release->version,
                'build_number' => $release->build_number,
                'old' => $original,
                'attributes' => $release->only($changedKeys),
            ])
            ->log('app_release_updated');

        return $release->refresh();
    }
}
