<?php

namespace App\Modules\AppDistribution\Actions;

use App\Models\User;
use App\Modules\AppDistribution\Data\ReleasePushStatus;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\AppDistribution\Models\AppReleasePushCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function activity;

/**
 * Crée la campagne FCM associée à une release store (#342).
 *
 * Idempotence : une release porte au plus une campagne. Un double clic ou
 * deux requêtes concurrentes renvoient la campagne existante ; la contrainte
 * d'unicité en base reste le garde-fou ultime. Le job d'envoi est dispatché
 * après validation de la transaction.
 */
class DispatchReleasePushCampaignAction
{
    /**
     * @param  array{title: string, body: string, data: array<string, string>}  $message
     */
    public function execute(AppRelease $release, User $actor, array $message): AppReleasePushCampaign
    {
        if (! $release->isStorePubliclyVisible()) {
            throw ValidationException::withMessages([
                'release' => 'La release doit être confirmée et son rollout terminé avant toute campagne push.',
            ]);
        }

        return DB::transaction(function () use ($release, $actor, $message): AppReleasePushCampaign {
            $lockedRelease = AppRelease::query()->whereKey($release->getKey())->lockForUpdate()->firstOrFail();
            $existing = $lockedRelease->pushCampaign;

            if ($existing !== null) {
                return $existing;
            }

            $campaign = AppReleasePushCampaign::query()->create([
                'release_id' => $lockedRelease->getKey(),
                'app' => $lockedRelease->app,
                'platform' => $lockedRelease->platform,
                'environment' => $lockedRelease->environment,
                'topic' => $lockedRelease->topicName(),
                'status' => ReleasePushStatus::Pending,
                'title' => $message['title'],
                'body' => $message['body'],
                'data' => $message['data'],
                'attempts' => 0,
                'created_by' => $actor->id,
            ]);

            activity()
                ->performedOn($lockedRelease)
                ->causedBy($actor)
                ->withProperties([
                    'campaign_uuid' => $campaign->uuid,
                    'topic' => $campaign->topic,
                    'app' => $lockedRelease->app->value,
                    'platform' => $lockedRelease->platform?->value,
                    'environment' => $lockedRelease->environment?->value,
                ])
                ->log('app_release_push_campaign_created');

            return $campaign;
        });
    }
}
