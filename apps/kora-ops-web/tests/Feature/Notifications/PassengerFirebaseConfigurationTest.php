<?php

use App\Modules\Notifications\Services\FirebaseMessagingClient;
use App\Modules\Settings\Settings\IntegrationsSettings;

it('exposes public client Firebase identifiers without a session token', function (): void {
    $settings = app(IntegrationsSettings::class);
    $settings->firebasePushEnabled = true;
    $settings->firebaseProjectId = 'kora-test';
    $settings->firebaseWebApiKey = 'AIzaSyExamplePublicKey';
    $settings->firebaseWebMessagingSenderId = '123456789012';
    $settings->firebaseAndroidAppId = '1:123456789012:android:abcdef123456';
    $settings->firebaseIosAppId = '1:123456789012:ios:abcdef123456';
    $settings->firebaseServiceAccountJson = '{"private_key":"never-send-this"}';
    $settings->save();

    $firebase = Mockery::mock(FirebaseMessagingClient::class);
    $firebase->shouldReceive('isServerConfigured')->andReturnTrue();
    $this->app->instance(FirebaseMessagingClient::class, $firebase);

    $this->getJson('/api/v1/passenger/firebase-configuration?platform=android')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.api_key', 'AIzaSyExamplePublicKey')
        ->assertJsonPath('data.project_id', 'kora-test')
        ->assertJsonPath('data.messaging_sender_id', '123456789012')
        ->assertJsonPath('data.app_id', '1:123456789012:android:abcdef123456')
        ->assertJsonMissing(['private_key', 'service_account', 'firebaseServiceAccountJson']);
});

it('returns disabled when Firebase is not configured', function (): void {
    $firebase = Mockery::mock(FirebaseMessagingClient::class);
    $firebase->shouldReceive('isServerConfigured')->andReturnFalse();
    $this->app->instance(FirebaseMessagingClient::class, $firebase);

    $this->getJson('/api/v1/passenger/firebase-configuration?platform=ios')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonMissing(['api_key', 'project_id', 'messaging_sender_id', 'app_id']);
});

it('rejects an unknown platform', function (): void {
    $firebase = Mockery::mock(FirebaseMessagingClient::class);
    $firebase->shouldReceive('isServerConfigured')->andReturnTrue();
    $this->app->instance(FirebaseMessagingClient::class, $firebase);

    $this->getJson('/api/v1/passenger/firebase-configuration?platform=windows')
        ->assertUnprocessable();
});
