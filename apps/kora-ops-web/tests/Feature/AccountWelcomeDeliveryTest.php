<?php

use App\Models\User;
use App\Modules\IdentityAccess\Jobs\SendAccountWelcomeJob;
use App\Modules\Notifications\Channels\OtpCodeChannel;
use App\Notifications\IdentityAccess\WelcomeToKoraNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;

it('sends the current temporary password only to the intended active web account', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'password' => 'TemporaryPassword123',
        'must_change_password' => true,
    ]);

    $job = new SendAccountWelcomeJob($user->uuid, 'TemporaryPassword123', $user->email);
    expect($job)->toBeInstanceOf(ShouldBeEncrypted::class);
    $job->handle();

    Notification::assertSentTo($user, WelcomeToKoraNotification::class, function (WelcomeToKoraNotification $notification) use ($user): bool {
        $message = $notification->toMail($user);

        expect($message->actionUrl)->toBe(route('login'))
            ->and($message->subject)->toBe('Bienvenue dans l’équipe Kora Express')
            ->and($message->viewData['welcomeCredentials'])->toMatchArray([
                'Identifiant' => $user->email,
                'Mot de passe provisoire' => 'TemporaryPassword123',
            ]);

        $markdown = app(Markdown::class);
        expect($markdown->render($message->markdown, $message->data())->toHtml())->toContain('Vos accès', 'TemporaryPassword123')
            ->and($markdown->renderText($message->markdown, $message->data())->toHtml())->toContain('Vos accès', 'TemporaryPassword123');

        return true;
    });

    $user->forceFill(['password' => 'RotatedPassword123'])->save();
    $job->handle();

    Notification::assertCount(1);
});

it('welcomes a mobile passenger without an email in the app inbox once', function (): void {
    $user = User::factory()->create(['email' => 'traveler+test@'.OtpCodeChannel::PLACEHOLDER_EMAIL_DOMAIN]);
    $job = new SendAccountWelcomeJob($user->uuid, passengerApp: true);

    $job->handle();
    $job->handle();

    $notification = $user->notifications()->sole();

    expect($notification->data['title'])->toBe(WelcomeToKoraNotification::PASSENGER_SUBJECT)
        ->and($notification->data['message'])->not->toContain('Mot de passe');
});

it('sends a welcome email without a provisional password for a passenger registration', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    (new SendAccountWelcomeJob($user->uuid, passengerApp: true))->handle();

    Notification::assertSentTo($user, WelcomeToKoraNotification::class, function (WelcomeToKoraNotification $notification) use ($user): bool {
        $message = $notification->toMail($user);

        expect($message->actionUrl)->toBeNull()
            ->and($message->subject)->toBe(WelcomeToKoraNotification::PASSENGER_SUBJECT)
            ->and($message->viewData['welcomeCredentials'])->toBe([]);

        return true;
    });
});
