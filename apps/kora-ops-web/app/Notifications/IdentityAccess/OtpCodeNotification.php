<?php

namespace App\Notifications\IdentityAccess;

use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\Notifications\Channels\OtpCodeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [15, 90, 300];

    public ?string $challengeUuid = null;

    public ?float $queuedAt = null;

    public function __construct(
        private readonly string $code,
        private readonly OtpPurpose $purpose,
        private readonly int $expiresInMinutes = 5,
    ) {
        $this->onQueue('otp');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [OtpCodeChannel::class];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "Votre code KORA est {$this->code}. Il expire dans {$this->expiresInMinutes} minutes. Ne le partagez jamais.";
    }

    public function toSms(object $notifiable): string
    {
        return "Votre code KORA est {$this->code}. Il expire dans {$this->expiresInMinutes} minutes.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $instruction = $this->purpose === OtpPurpose::LoginNewDevice
            ? 'Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.'
            : 'Ne partagez jamais ce code avec une autre personne.';

        return (new MailMessage)
            ->subject('Votre code de vérification KORA')
            ->markdown('notifications::email', ['preview' => 'Utilisez le code contenu dans ce message pour poursuivre votre vérification.'])
            ->greeting('Bonjour,')
            ->line("Votre code de vérification KORA est **{$this->code}**. Il expire dans {$this->expiresInMinutes} minutes. {$instruction}");
    }
}
