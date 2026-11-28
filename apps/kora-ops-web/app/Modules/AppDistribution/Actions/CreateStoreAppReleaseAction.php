<?php

namespace App\Modules\AppDistribution\Actions;

use App\Models\User;
use App\Modules\AppDistribution\Data\ReleaseChannel;
use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Data\ReleaseEnvironment;
use App\Modules\AppDistribution\Data\ReleasePlatform;
use App\Modules\AppDistribution\Models\AppRelease;

use function activity;

/**
 * Crée une release store Passager (#342) sans binaire ni téléversement APK.
 *
 * La release est enregistrée comme candidate, mais reste absente de l'endpoint
 * public tant que la confirmation Ops et la fin de rollout ne sont pas
 * marquées via ConfirmStoreReleaseAction.
 */
class CreateStoreAppReleaseAction
{
    /**
     * @param  array{platform: ReleasePlatform, environment: ReleaseEnvironment, store_url: string, version: string, build_number: int, release_notes: string|null, is_mandatory: bool}  $attributes
     */
    public function execute(array $attributes, User $actor): AppRelease
    {
        $release = AppRelease::query()->create([
            'app' => ReleasedApp::PassengerMobile,
            'channel' => ReleaseChannel::Store,
            'platform' => $attributes['platform'],
            'environment' => $attributes['environment'],
            'store_url' => $attributes['store_url'],
            'version' => $attributes['version'],
            'build_number' => $attributes['build_number'],
            'artifact_path' => '',
            'original_filename' => null,
            'artifact_size' => 0,
            'sha256' => '',
            'release_notes' => $attributes['release_notes'],
            'is_mandatory' => $attributes['is_mandatory'],
            'published_at' => now(),
            'created_by' => $actor->id,
        ]);

        activity()
            ->performedOn($release)
            ->causedBy($actor)
            ->withProperties([
                'app' => ReleasedApp::PassengerMobile->value,
                'platform' => $attributes['platform']->value,
                'environment' => $attributes['environment']->value,
                'version' => $release->version,
                'build_number' => $release->build_number,
                'store_url' => $attributes['store_url'],
            ])
            ->log('app_store_release_created');

        return $release->refresh();
    }
}
