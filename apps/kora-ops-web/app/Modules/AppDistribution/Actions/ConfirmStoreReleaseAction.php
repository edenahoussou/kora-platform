<?php

namespace App\Modules\AppDistribution\Actions;

use App\Models\User;
use App\Modules\AppDistribution\Models\AppRelease;
use Illuminate\Validation\ValidationException;

use function activity;

/**
 * Confirme publiquement une release store (#342).
 *
 * Cette action est l'audit explicite avant tout envoi push. Elle enregistre
 * la confirmation Ops et, si applicable, la fin du rollout progressif. La
 * release ne devient publique que lorsque ces deux horodatages existent.
 */
class ConfirmStoreReleaseAction
{
    public function execute(AppRelease $release, bool $rolloutCompleted, User $actor): AppRelease
    {
        if (! $release->isStore()) {
            throw ValidationException::withMessages([
                'release' => 'Seule une release store peut être confirmée par cette action.',
            ]);
        }

        $release->forceFill([
            'publicly_confirmed_at' => $release->publicly_confirmed_at ?? now(),
            'rollout_completed_at' => $rolloutCompleted ? now() : $release->rollout_completed_at,
        ])->save();

        activity()
            ->performedOn($release)
            ->causedBy($actor)
            ->withProperties([
                'app' => $release->app->value,
                'platform' => $release->platform?->value,
                'environment' => $release->environment?->value,
                'version' => $release->version,
                'build_number' => $release->build_number,
                'rollout_completed' => $rolloutCompleted,
            ])
            ->log('app_store_release_confirmed');

        return $release->refresh();
    }
}
