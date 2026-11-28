<?php

namespace App\Modules\AppDistribution\Actions;

use App\Models\User;
use App\Modules\AppDistribution\Data\ReleasePushStatus;
use App\Modules\AppDistribution\Models\AppReleasePushCampaign;
use Illuminate\Validation\ValidationException;

use function activity;

/**
 * Enregistre la décision Ops explicite avant relance d'une campagne
 * incertaine (#342). Sans cette décision, aucune relance n'est possible.
 */
class DecideRetryPushCampaignAction
{
    public function execute(AppReleasePushCampaign $campaign, ?string $note, User $actor): AppReleasePushCampaign
    {
        if ($campaign->status !== ReleasePushStatus::Uncertain) {
            throw ValidationException::withMessages([
                'campaign' => 'Seule une campagne incertaine peut faire l’objet d’une décision de relance.',
            ]);
        }

        $campaign->forceFill([
            'retry_decision_by' => $actor->id,
            'retry_decision_at' => now(),
            'retry_decision_note' => $note,
        ])->save();

        activity()
            ->performedOn($campaign->release)
            ->causedBy($actor)
            ->withProperties([
                'campaign_uuid' => $campaign->uuid,
                'topic' => $campaign->topic,
                'note' => $note,
            ])
            ->log('app_release_push_retry_decided');

        return $campaign->refresh();
    }
}
