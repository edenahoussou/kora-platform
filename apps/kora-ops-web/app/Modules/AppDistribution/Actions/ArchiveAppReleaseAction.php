<?php

namespace App\Modules\AppDistribution\Actions;

use App\Models\User;
use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Validation\ValidationException;

use function activity;

/**
 * Archive (dépublie) une release : elle sort immédiatement du calcul
 * « dernière version » et son lien de téléchargement direct cesse de
 * répondre. Le binaire est conservé pour permettre une restauration
 * (rollback de l'archivage) via RestoreAppReleaseAction.
 *
 * Archiver la dernière release fait redevenir « dernière » la précédente
 * publiée. Les appareils déjà passés à la version archivée ne sont jamais
 * forcés de rétrograder (Android refuse d'ailleurs un versionCode
 * inférieur) : pour corriger un package défectueux, publier une nouvelle
 * release au build supérieur.
 */
class ArchiveAppReleaseAction
{
    public function execute(AppRelease $release, User $actor): AppRelease
    {
        if ($release->isArchived()) {
            throw ValidationException::withMessages([
                'release' => 'Cette version est déjà archivée.',
            ]);
        }

        $release->forceFill(['archived_at' => now()])->save();

        activity()
            ->performedOn($release)
            ->causedBy($actor)
            ->withProperties([
                'app' => $release->app->value,
                'version' => $release->version,
                'build_number' => $release->build_number,
                'was_mandatory' => $release->is_mandatory,
            ])
            ->log('app_release_archived');

        return $release->refresh();
    }
}
