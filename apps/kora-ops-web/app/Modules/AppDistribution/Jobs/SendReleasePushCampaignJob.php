<?php

namespace App\Modules\AppDistribution\Jobs;

use App\Modules\AppDistribution\Data\ReleasePushStatus;
use App\Modules\AppDistribution\Models\AppReleasePushCampaign;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kreait\Firebase\Exception\Messaging\ApiConnectionFailed;
use Kreait\Firebase\Exception\Messaging\AuthenticationError;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\QuotaExceeded;
use Kreait\Firebase\Exception\Messaging\SenderIdMismatch;
use Kreait\Firebase\Exception\Messaging\ServerError;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use RuntimeException;
use Throwable;

/**
 * Envoie une campagne FCM par topic (#342).
 *
 * Le job est unique par campagne et ne réessaie pas automatiquement : un
 * échec `uncertain` reste bloqué tant qu'une décision Ops explicite n'est
 * pas enregistrée. Les erreurs de configuration/validation sont marquées
 * `failed` ; les erreurs réseau ou serveur temporaires sont `uncertain`.
 */
class SendReleasePushCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $campaignId) {}

    public function handle(FirebaseMessagingClient $firebase): void
    {
        $campaign = AppReleasePushCampaign::query()->find($this->campaignId);

        if ($campaign === null || $campaign->status === ReleasePushStatus::Accepted) {
            return;
        }

        if (! $firebase->isServerConfigured()) {
            $this->mark($campaign, ReleasePushStatus::Failed, 'firebase_not_configured');

            return;
        }

        try {
            $messageId = $firebase->sendToTopic($campaign->topic, [
                'title' => $campaign->title,
                'body' => $campaign->body,
                'platform' => $campaign->platform->value,
                'data' => $this->payloadData($campaign),
            ]);

            $campaign->forceFill([
                'status' => ReleasePushStatus::Accepted,
                'fcm_message_id' => $messageId,
                'error' => null,
                'accepted_at' => now(),
            ])->save();
        } catch (NotFound|SenderIdMismatch|InvalidArgument|InvalidMessage|AuthenticationError|QuotaExceeded $exception) {
            $this->mark($campaign, ReleasePushStatus::Failed, $exception->getMessage());
        } catch (ApiConnectionFailed|ServerUnavailable|ServerError|RuntimeException $exception) {
            $this->mark($campaign, ReleasePushStatus::Uncertain, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->mark($campaign, ReleasePushStatus::Uncertain, $exception->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    private function payloadData(AppReleasePushCampaign $campaign): array
    {
        return array_filter([
            'type' => 'app_release_update',
            'release_uuid' => $campaign->release?->uuid,
            'app' => $campaign->app->value,
            'platform' => $campaign->platform->value,
            'environment' => $campaign->environment->value,
            'version' => $campaign->release?->version,
            'build' => (string) $campaign->release?->build_number,
            'store_url' => $campaign->release?->store_url,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }

    private function mark(AppReleasePushCampaign $campaign, ReleasePushStatus $status, string $error): void
    {
        $campaign->forceFill([
            'status' => $status,
            'error' => mb_strimwidth($error, 0, 500, ''),
            'attempts' => $campaign->attempts + 1,
        ])->save();
    }
}
