<?php

namespace App\Notifications\Passengers;

use App\Modules\Notifications\Channels\PassengerErasureChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmation envoyée au passager après effacement définitif de son
 * compte : email en priorité, WhatsApp en repli.
 */
class PassengerAccountErasedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [15, 90, 300];

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [PassengerErasureChannel::class];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return 'KORA : votre compte et vos données personnelles ont été définitivement supprimés à la suite de la demande d’effacement traitée par nos services. Si vous n’êtes pas à l’origine de cette demande, contactez le support KORA.';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Suppression de votre compte KORA')
            ->markdown('notifications::email', ['preview' => 'La suppression de votre compte passager a été traitée.'])
            ->greeting('Bonjour,')
            ->line('Votre compte KORA et vos données personnelles ont été définitivement supprimés à la suite de la demande d’effacement traitée par nos services.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, contactez le support KORA.');
    }
}
