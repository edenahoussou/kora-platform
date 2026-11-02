<?php

namespace App\Notifications\IdentityAccess;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

final class KoraPointWebAccessNotification extends Notification
{
    public function __construct(
        private readonly string $name,
        private readonly string $role,
        private readonly string $pointOrOrganization,
        private readonly string $email,
        #[SensitiveParameter] private readonly string $temporaryPassword,
        private readonly string $loginUrl,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Vos accès web KORA POINT')
            ->greeting('Bonjour '.$this->name.',')
            ->line('Votre compte web KORA POINT est prêt.')
            ->line('Nom : '.$this->name)
            ->line('Rôle : '.$this->role)
            ->line('Point ou organisation : '.$this->pointOrOrganization)
            ->line('E-mail de connexion : '.$this->email)
            ->line('Mot de passe provisoire : '.$this->temporaryPassword)
            ->action('Se connecter à KORA OPS', $this->loginUrl)
            ->line('Ce mot de passe expire 72 heures après son envoi.')
            ->line('Vous devez le changer dès votre première connexion.');
    }

    public function toSms(): string
    {
        return implode("\n", [
            'KORA OPS - accès web initial',
            'Nom : '.$this->name,
            'Rôle : '.$this->role,
            'Point/organisation : '.$this->pointOrOrganization,
            'Connexion : '.$this->email,
            'Mot de passe provisoire : '.$this->temporaryPassword,
            'URL : '.$this->loginUrl,
            'Expire 72 h après envoi. Changez-le à la première connexion.',
        ]);
    }
}
