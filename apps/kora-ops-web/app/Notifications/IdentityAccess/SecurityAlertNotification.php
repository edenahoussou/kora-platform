<?php

namespace App\Notifications\IdentityAccess;

use App\Modules\IdentityAccess\Data\SecurityAlertEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $context
     */
    public function __construct(
        private readonly SecurityAlertEvent $event,
        private readonly array $context = [],
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
        $mail = (new MailMessage)
            ->subject('[KORA OPS] '.$this->event->subject())
            ->markdown('notifications::email', ['preview' => 'Consultez les détails de cette activité sur votre compte.'])
            ->greeting('Alerte de sécurité')
            ->lines($this->event->lines());

        if (! empty($this->context['ip'])) {
            $mail->line('Adresse IP : '.$this->context['ip']);
        }

        if (! empty($this->context['user_agent'])) {
            $mail->line('Appareil : '.$this->context['user_agent']);
        }

        return $mail
            ->line('Si vous n\'êtes pas à l\'origine de cette action, contactez immédiatement la Direction des Systèmes d\'Information et réinitialisez votre mot de passe.')
            ->salutation('KORA OPS — Sécurité des comptes');
    }
}
