<?php

namespace App\Modules\IdentityAccess\Jobs;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\Notifications\Channels\OtpCodeChannel;
use App\Modules\Notifications\Notifications\PassengerInboxNotification;
use App\Notifications\IdentityAccess\WelcomeToKoraNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use SensitiveParameter;

class SendAccountWelcomeJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $userUuid,
        #[SensitiveParameter] private readonly ?string $temporaryPassword = null,
        private readonly ?string $loginEmail = null,
        private readonly bool $passengerApp = false,
    ) {}

    public function handle(): void
    {
        $user = User::query()->where('uuid', $this->userUuid)->first();

        if ($user === null || $user->status !== UserStatus::Active) {
            return;
        }

        if ($this->temporaryPassword !== null && (
            ! $user->must_change_password
            || $user->email !== $this->loginEmail
            || ! Hash::check($this->temporaryPassword, $user->password)
        )) {
            return;
        }

        if (Str::endsWith($user->email, '@'.OtpCodeChannel::PLACEHOLDER_EMAIL_DOMAIN)) {
            if ($this->passengerApp) {
                Notification::sendNow($user, new PassengerInboxNotification(
                    dedupeKey: 'account-welcome:'.$user->uuid,
                    type: 'account_welcome',
                    title: WelcomeToKoraNotification::PASSENGER_SUBJECT,
                    message: 'Une visite en famille ou une envie de changer d’air ? Consultez les prochains départs, choisissez votre place et retrouvez votre billet dans l’application. À bientôt à bord !',
                ), ['passengerDatabase']);
            }

            return;
        }

        $user->notifyNow(new WelcomeToKoraNotification(
            $user->name,
            $user->email,
            $this->temporaryPassword,
            $this->passengerApp || $user->hasRole('passenger'),
        ));
    }
}
