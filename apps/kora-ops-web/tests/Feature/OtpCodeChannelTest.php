<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Exceptions\OtpDeliveryException;
use App\Modules\Notifications\Channels\OtpCodeChannel;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Notifications\IdentityAccess\OtpCodeNotification;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function otpChannelSettings(bool $genukaConfigured = true): IntegrationsSettings
{
    $settings = new IntegrationsSettings;
    $settings->genukaUrl = 'https://wa.genuka.com';
    $settings->genukaToken = $genukaConfigured ? 'genuka-token' : null;
    $settings->genukaConnectionId = $genukaConfigured ? 'connection-id' : null;
    $settings->wahaUrl = 'http://waha.test';
    $settings->wahaToken = 'waha-token';
    $settings->wahaSession = 'default';

    return $settings;
}

it('delivers otp through Genuka WhatsApp when a phone is available', function (): void {
    app()->instance(IntegrationsSettings::class, otpChannelSettings());
    Http::fake([
        'https://wa.genuka.com/*' => Http::response(['id' => 'otp-genuka-001'], 200),
    ]);

    $user = User::factory()->create(['phone' => '+2290197421860']);
    $notification = new OtpCodeNotification('123456', OtpPurpose::LoginNewDevice);

    app(OtpCodeChannel::class)->send($user, $notification);

    Http::assertSent(function (Request $request): bool {
        $payload = json_decode($request->body(), true);

        return str_contains($request->url(), 'wa.genuka.com')
            && ($payload['to'] ?? null) === '+2290197421860'
            && str_contains((string) ($payload['text'] ?? ''), '123456');
    });
});

it('falls back to email when Genuka and WAHA both fail', function (): void {
    app()->instance(IntegrationsSettings::class, otpChannelSettings());
    Http::fake([
        'https://wa.genuka.com/*' => Http::response(['error' => 'unavailable'], 500),
        'http://waha.test/*' => Http::response(['error' => 'unavailable'], 500),
    ]);

    $mail = Mockery::mock(MailChannel::class);
    $mail->shouldReceive('send')->once();
    app()->instance(MailChannel::class, $mail);

    $user = User::factory()->create(['phone' => '+2290197421860']);
    app(OtpCodeChannel::class)->send(
        $user,
        new OtpCodeNotification('654321', OtpPurpose::PinReset),
    );

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'wa.genuka.com'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'waha.test'));
});

it('uses email directly when the account has no phone', function (): void {
    $mail = Mockery::mock(MailChannel::class);
    $mail->shouldReceive('send')->once();
    app()->instance(MailChannel::class, $mail);

    $user = User::factory()->create(['phone' => null]);
    app(OtpCodeChannel::class)->send(
        $user,
        new OtpCodeNotification('111222', OtpPurpose::AccountUnlock),
    );
});

it('sends through WhatsApp for a notifiable routed by phone', function (): void {
    app()->instance(IntegrationsSettings::class, otpChannelSettings());
    Http::fake([
        'https://wa.genuka.com/*' => Http::response(['id' => 'otp-wa-001'], 200),
    ]);

    $notifiable = Notification::route('whatsapp', '+2290153234060');
    app(OtpCodeChannel::class)->send(
        $notifiable,
        new OtpCodeNotification('445566', OtpPurpose::Registration),
    );

    Http::assertSent(function (Request $request): bool {
        $payload = json_decode($request->body(), true);

        return str_contains($request->url(), 'wa.genuka.com')
            && ($payload['to'] ?? null) === '+2290153234060'
            && str_contains((string) ($payload['text'] ?? ''), '445566');
    });
});

it('throws otp_delivery_unavailable when WhatsApp fails and the email is a placeholder', function (): void {
    app()->instance(IntegrationsSettings::class, otpChannelSettings());
    Http::fake([
        'https://wa.genuka.com/*' => Http::response(['error' => 'unavailable'], 500),
        'http://waha.test/*' => Http::response(['error' => 'unavailable'], 500),
    ]);

    $mail = Mockery::mock(MailChannel::class);
    $mail->shouldReceive('send')->never();
    app()->instance(MailChannel::class, $mail);

    $user = User::factory()->create([
        'phone' => '+2290153234060',
        'email' => 'traveler+'.Str::uuid().'@accounts.kora.invalid',
    ]);

    app(OtpCodeChannel::class)->send(
        $user,
        new OtpCodeNotification('789012', OtpPurpose::PinReset),
    );
})->throws(OtpDeliveryException::class);
