<?php

namespace App\Modules\AppDistribution\Actions;

use App\Models\User;
use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function activity;

/**
 * Republie une release archivée : elle redevient candidate au calcul
 * « dernière version » et son lien direct répond à nouveau. Refusé si le
 * binaire n'est plus présent sur le disque (on ne republie jamais une
 * release dont le téléchargement échouerait).
 */
class RestoreAppReleaseAction
{
    public function execute(AppRelease $release, User $actor): AppRelease
    {
        if (! $release->isArchived()) {
            throw ValidationException::withMessages([
                'release' => 'Cette version est déjà publiée.',
            ]);
        }

        if (! $release->isStore() && ! Storage::disk(AppRelease::DISK)->exists($release->artifact_path)) {
            throw ValidationException::withMessages([
                'release' => 'Le package de cette version est introuvable sur le serveur : publiez une nouvelle version.',
            ]);
        }

        $release->forceFill([
            'archived_at' => null,
            'published_at' => $release->published_at ?? now(),
        ])->save();

        activity()
            ->performedOn($release)
            ->causedBy($actor)
            ->withProperties([
                'app' => $release->app->value,
                'version' => $release->version,
                'build_number' => $release->build_number,
                'is_mandatory' => $release->is_mandatory,
            ])
            ->log('app_release_restored');

        return $release->refresh();
    }
}
