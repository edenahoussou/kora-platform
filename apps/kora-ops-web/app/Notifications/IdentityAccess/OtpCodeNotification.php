<?php

namespace App\Notifications\IdentityAccess;

use App\Modules\IdentityAccess\Data\OtpPurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly OtpPurpose $purpose,
        private readonly int $expiresInMinutes = 5,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code de vérification KORA')
            ->greeting('Bonjour,')
            ->line('Voici votre code de vérification KORA :')
            ->line($this->code)
            ->line("Ce code expire dans {$this->expiresInMinutes} minutes.")
            ->line($this->purpose === OtpPurpose::LoginNewDevice
                ? 'Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.'
                : 'Ne partagez jamais ce code avec une autre personne.');
    }
}
