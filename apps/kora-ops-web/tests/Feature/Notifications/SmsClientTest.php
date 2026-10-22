<?php

use App\Modules\Notifications\Channels\SmsChannel;
use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Notifications\Notifications\PassengerSmsNotification;
use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;
use App\Modules\Notifications\Services\Sms\ConexteoSmsException;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function conexteoTestSettings(bool $enabled = true): IntegrationsSettings
{
    $settings = new IntegrationsSettings;
    $settings->conexteoEnabled = $enabled;
    $settings->conexteoAppId = 'app-123';
    $settings->conexteoApiKey = 'secret-api-key';
    $settings->conexteoSender = 'KoraExpress';

    return $settings;
}

it('sends an SMS with the Conexteo headers and payload', function (): void {
    app()->instance(IntegrationsSettings::class, conexteoTestSettings());
    Http::preventStrayRequests();
    Http::fake([
        'api.conexteo.com/*' => Http::response([
            'success' => true,
            'message_id' => 'msg-001',
            'credits_used' => 1.875,
        ]),
    ]);

    $result = app(ConexteoSmsClient::class)->send('+22997000000', 'Votre trajet est confirmé.', 'external-1');

    expect($result)->toBe([
        'message_id' => 'msg-001',
        'credits_used' => 1.875,
        'duplicate' => false,
    ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.conexteo.com/messages/sms'
        && $request->hasHeader('X-APP-ID', 'app-123')
        && $request->hasHeader('X-API-KEY', 'secret-api-key')
        && $request['content'] === 'Votre trajet est confirmé.'
        && $request['recipients'] === ['+22997000000']
        && $request['sender'] === 'KoraExpress'
        && $request['external_id'] === 'external-1');
});

it('converts the new Benin format to the legacy format for delivery', function (): void {
    app()->instance(IntegrationsSettings::class, conexteoTestSettings());
    Http::fake([
        'api.conexteo.com/*' => Http::response([
            'success' => true,
            'message_id' => 'msg-002',
            'credits_used' => 1,
        ]),
    ]);

    app(ConexteoSmsClient::class)->send('+2290166877345', 'Bonjour');

    Http::assertSent(fn (Request $request): bool => $request['recipients'] === ['+22966877345']);
});

it('treats a 409 as an already delivered duplicate', function (): void {
    app()->instance(IntegrationsSettings::class, conexteoTestSettings());
    Http::fake(['api.conexteo.com/*' => Http::response(['error' => 'duplicate'], 409)]);

    expect(app(ConexteoSmsClient::class)->send('+22997000000', 'Bonjour', 'external-1'))
        ->toBe(['message_id' => null, 'credits_used' => null, 'duplicate' => true]);
});

it('does not retry a definitive 4xx rejection', function (): void {
    app()->instance(IntegrationsSettings::class, conexteoTestSettings());
    Http::fake(['api.conexteo.com/*' => Http::response(['error' => 'unauthorized'], 401)]);

    try {
        app(ConexteoSmsClient::class)->send('+22997000000', 'Bonjour');
        $this->fail('Expected a ConexteoSmsException.');
    } catch (ConexteoSmsException $exception) {
        expect($exception->status())->toBe(401)
            ->and($exception->shouldRetry())->toBeFalse()
            ->and($exception->getMessage())->not->toContain('secret-api-key');
    }
});

it('retries throttling, server errors and timeouts', function (): void {
    app()->instance(IntegrationsSettings::class, conexteoTestSettings());
    Http::fake(['api.conexteo.com/*' => Http::response(['error' => 'throttled'], 429)]);

    try {
        app(ConexteoSmsClient::class)->send('+22997000000', 'Bonjour');
        $this->fail('Expected a ConexteoSmsException.');
    } catch (ConexteoSmsException $exception) {
        expect($exception->shouldRetry())->toBeTrue();
    }

    Http::fake(['api.conexteo.com/*' => fn () => throw new ConnectionException('timeout')]);

    try {
        app(ConexteoSmsClient::class)->send('+22997000000', 'Bonjour');
        $this->fail('Expected a ConexteoSmsException.');
    } catch (ConexteoSmsException $exception) {
        expect($exception->status())->toBeNull()
            ->and($exception->shouldRetry())->toBeTrue();
    }
});

it('reports a partial configuration as not configured', function (): void {
    $settings = conexteoTestSettings();
    $settings->conexteoApiKey = null;
    app()->instance(IntegrationsSettings::class, $settings);

    expect(app(ConexteoSmsClient::class)->isConfigured())->toBeFalse();
});

it('queues nothing when the channel is disabled', function (): void {
    app()->instance(IntegrationsSettings::class, conexteoTestSettings(enabled: false));
    Queue::fake();

    Notification::route('sms', '+22997000000')->notify(new PassengerSmsNotification(
        content: 'Bonjour',
        dedupeKey: 'bus:sms:reservation_confirmed:test',
        type: 'reservation_confirmed',
    ));

    Queue::assertNothingPushed();
});

it('queues the SMS job when the channel is configured', function (): void {
    app()->instance(IntegrationsSettings::class, conexteoTestSettings());
    Queue::fake();

    Notification::route('sms', '+22997000000')->notify(new PassengerSmsNotification(
        content: 'Bonjour',
        dedupeKey: 'bus:sms:reservation_confirmed:test',
        type: 'reservation_confirmed',
        referenceType: 'reservation',
        referenceId: 42,
    ));

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->recipient === '+22997000000'
        && $job->type === 'reservation_confirmed'
        && $job->referenceId === 42);
});

it('resolves the channel for the sms notification driver', function (): void {
    expect(app(SmsChannel::class))->toBeInstanceOf(SmsChannel::class);
});
