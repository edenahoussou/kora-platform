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
            ->subject('Bienvenue dans l’équipe Kora Express · KORA POINT')
            ->markdown('notifications::email', [
                'preview' => 'Votre accès web KORA POINT est prêt. Retrouvez vos identifiants pour votre première connexion.',
                'welcomeCredentials' => [
                    'Identifiant' => $this->email,
                    'Mot de passe provisoire' => $this->temporaryPassword,
                ],
            ])
            ->greeting('Bonjour '.$this->name.',')
            ->line('Bienvenue dans l’équipe Kora Express ! Nous sommes ravis de vous accueillir.')
            ->line('Votre espace KORA POINT est prêt. Vous y retrouverez les outils utiles à votre mission.')
            ->line('Rôle : '.$this->role)
            ->line('Point ou organisation : '.$this->pointOrOrganization)
            ->action('Se connecter à KORA OPS', $this->loginUrl)
            ->line('Ce mot de passe expire 72 heures après son envoi.')
            ->line('À votre première connexion, vous serez invité à choisir votre mot de passe personnel.')
            ->line('En cas de difficulté, contactez votre responsable ou l’administrateur qui a créé votre compte.')
            ->salutation("Cordialement,\n\nL’équipe Kora Express");
    }
}
