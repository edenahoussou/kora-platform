<?php

namespace App\Notifications\IdentityAccess;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use SensitiveParameter;

class WelcomeToKoraNotification extends Notification
{
    public const string PASSENGER_SUBJECT = 'Bienvenue à bord de Kora Express';

    public function __construct(
        private readonly string $name,
        private readonly string $email,
        #[SensitiveParameter] private readonly ?string $temporaryPassword,
        private readonly bool $passenger,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = Str::before(trim($this->name), ' ');
        $preview = $this->passenger
            ? 'Retrouvez vos trajets, réservez votre place et gardez votre billet à portée de main.'
            : 'Votre accès à KORA OPS est prêt. Retrouvez vos identifiants pour votre première connexion.';

        $message = (new MailMessage)
            ->subject($this->passenger ? self::PASSENGER_SUBJECT : 'Bienvenue dans l’équipe Kora Express')
            ->markdown('notifications::email', [
                'preview' => $preview,
                'welcomeCredentials' => $this->temporaryPassword === null ? [] : [
                    'Identifiant' => $this->email,
                    'Mot de passe provisoire' => $this->temporaryPassword,
                ],
            ])
            ->greeting(filled($firstName) ? 'Bonjour '.$firstName.',' : 'Bonjour,');

        if ($this->passenger) {
            $message->line('Nous sommes ravis de vous accueillir sur l’application Kora Express.')
                ->line('Une visite en famille, un rendez-vous ou une envie de changer d’air ? Votre prochain voyage commence ici. Depuis l’application, vous pouvez consulter les départs, choisir votre place et retrouver votre billet au moment de monter à bord.')
                ->line('Pour découvrir les prochains départs, ouvrez l’application Kora Express.');

            if ($this->temporaryPassword !== null) {
                $message->action('Accéder à mon compte', route('login'))
                    ->line('À votre première connexion web, vous serez invité à choisir votre mot de passe personnel.');
            }

            return $message->salutation("Au plaisir de vous retrouver à bord,\n\nL’équipe Kora Express");
        }

        $message->line('Bienvenue dans l’équipe Kora Express ! Nous sommes ravis de vous accueillir.')
            ->line('Votre espace KORA OPS est prêt. Vous y retrouverez les outils et les informations utiles à votre mission, selon le rôle qui vous a été attribué.')
            ->action('Se connecter à KORA OPS', route('login'));

        if ($this->temporaryPassword !== null) {
            $message->line('À votre première connexion, vous serez invité à choisir votre mot de passe personnel.');
        }

        return $message->line('Si vous rencontrez une difficulté ou si votre rôle ne correspond pas à votre mission, contactez votre responsable ou l’administrateur qui a créé votre compte.')
            ->line('Nous vous souhaitons une excellente prise en main de votre espace et sommes heureux de vous compter parmi nous.')
            ->salutation("Cordialement,\n\nL’équipe Kora Express");
    }
}
