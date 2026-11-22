<?php

namespace App\Notifications\Passengers;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte ops : un passager demande la suppression de son compte depuis la
 * page publique. Traitement via Passagers → fiche passager → Effacer.
 */
class PassengerDeletionRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [15, 90, 300];

    public function __construct(
        public readonly string $phone,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly ?string $reason,
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
            ->subject('Demande de suppression de compte passager')
            ->markdown('notifications::email', ['preview' => 'Nous avons reçu votre demande de suppression de compte passager.'])
            ->greeting('Bonjour,')
            ->line('Une demande de suppression de compte a été déposée depuis la page publique KORA.')
            ->line('Téléphone du compte : '.$this->phone);

        if ($this->name !== null) {
            $mail->line('Nom déclaré : '.$this->name);
        }
        if ($this->email !== null) {
            $mail->line('E-mail de contact : '.$this->email);
        }
        if ($this->reason !== null) {
            $mail->line('Motif : '.$this->reason);
        }

        return $mail->line('Traitement attendu sous 30 jours : Passagers → recherche par téléphone → Effacer.');
    }
}
