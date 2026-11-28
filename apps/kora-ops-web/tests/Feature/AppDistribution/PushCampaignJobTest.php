<?php

use App\Modules\AppDistribution\Data\ReleasedApp;
use App\Modules\AppDistribution\Data\ReleaseEnvironment;
use App\Modules\AppDistribution\Data\ReleasePlatform;
use App\Modules\AppDistribution\Data\ReleasePushStatus;
use App\Modules\AppDistribution\Jobs\SendReleasePushCampaignJob;
use App\Modules\AppDistribution\Models\AppRelease;
use App\Modules\AppDistribution\Models\AppReleasePushCampaign;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;

function makePushCampaign(): AppReleasePushCampaign
{
    $release = AppRelease::factory()
        ->storeRelease(ReleasePlatform::Android)
        ->version('1.4.0', 42)
        ->confirmed()
        ->create();

    return AppReleasePushCampaign::query()->create([
        'release_id' => $release->id,
        'app' => ReleasedApp::PassengerMobile,
        'platform' => ReleasePlatform::Android,
        'environment' => ReleaseEnvironment::Production,
        'topic' => $release->topicName(),
        'status' => ReleasePushStatus::Pending->value,
        'title' => 'Mise à jour KORA Passager',
        'body' => 'La nouvelle version est disponible.',
        'data' => [],
        'attempts' => 0,
    ]);
}

it('marks an accepted FCM topic delivery with its message identifier', function (): void {
    $campaign = makePushCampaign();
    $client = Mockery::mock(FirebaseMessagingClient::class);
    $client->shouldReceive('isServerConfigured')->andReturnTrue();
    $client->shouldReceive('sendToTopic')->once()
        ->with('passenger-android-prod', Mockery::on(function (array $message) use ($campaign): bool {
            $data = $message['data'] ?? [];

            return ($data['type'] ?? null) === 'app_update'
                && ($data['release_uuid'] ?? null) === $campaign->release?->uuid
                && ($data['platform'] ?? null) === 'android'
                && ($data['environment'] ?? null) === 'production'
                && ($data['version'] ?? null) === '1.4.0'
                && ($data['build'] ?? null) === '42'
                && ($data['store_url'] ?? null) === $campaign->release?->store_url;
        }))
        ->andReturn('projects/kora/messages/abc-123');
    $this->app->instance(FirebaseMessagingClient::class, $client);

    (new SendReleasePushCampaignJob($campaign->id))->handle(app(FirebaseMessagingClient::class));

    $campaign->refresh();

    expect($campaign->topic)->toBe('passenger-android-prod')
        ->and($campaign->status)->toBe(ReleasePushStatus::Accepted)
        ->and($campaign->fcm_message_id)->toBe('projects/kora/messages/abc-123')
        ->and($campaign->accepted_at)->not->toBeNull()
        ->and($campaign->error)->toBeNull();
});

it('marks an invalid message as failed without an automatic retry', function (): void {
    $campaign = makePushCampaign();
    $client = Mockery::mock(FirebaseMessagingClient::class);
    $client->shouldReceive('isServerConfigured')->andReturnTrue();
    $client->shouldReceive('sendToTopic')->once()->andThrow(new InvalidArgument('bad message'));
    $this->app->instance(FirebaseMessagingClient::class, $client);

    (new SendReleasePushCampaignJob($campaign->id))->handle(app(FirebaseMessagingClient::class));

    $campaign->refresh();

    expect($campaign->status)->toBe(ReleasePushStatus::Failed)
        ->and($campaign->attempts)->toBe(1)
        ->and($campaign->error)->toContain('bad message');
});

it('marks a temporary FCM failure as uncertain and blocks retry until an Ops decision', function (): void {
    $campaign = makePushCampaign();
    $client = Mockery::mock(FirebaseMessagingClient::class);
    $client->shouldReceive('isServerConfigured')->andReturnTrue();
    $client->shouldReceive('sendToTopic')->once()->andThrow(new ServerUnavailable('temporary unavailable'));
    $this->app->instance(FirebaseMessagingClient::class, $client);

    (new SendReleasePushCampaignJob($campaign->id))->handle(app(FirebaseMessagingClient::class));

    $campaign->refresh();

    expect($campaign->status)->toBe(ReleasePushStatus::Uncertain)
        ->and($campaign->retry_decision_at)->toBeNull()
        ->and($campaign->error)->toContain('temporary unavailable');
});
