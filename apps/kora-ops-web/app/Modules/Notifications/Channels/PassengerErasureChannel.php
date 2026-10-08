<?php

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Services\WhatsApp\WhatsAppMessageDispatcher;
use App\Notifications\Passengers\PassengerAccountErasedNotification;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Log;

/**
 * Livraison de la confirmation d'effacement : email en priorité quand
 * l'adresse est joignable, WhatsApp sinon.
 */
class PassengerErasureChannel
{
    public function __construct(
        private readonly WhatsAppMessageDispatcher $whatsapp,
        private readonly MailChannel $mail,
    ) {}

    public function send(object $notifiable, PassengerAccountErasedNotification $notification): void
    {
        $email = $this->routeNotificationTarget($notifiable, 'mail');

        if (OtpCodeChannel::isContactableEmail(is_string($email) ? $email : null)) {
            $this->mail->send($notifiable, $notification);

            return;
        }

        $phone = $this->routeNotificationTarget($notifiable, 'whatsapp');

        if (is_string($phone) && trim($phone) !== '') {
            $this->whatsapp->send($phone, $notification->toWhatsApp($notifiable));

            return;
        }

        Log::warning('Passenger erasure notice skipped: no reachable email or phone.');
    }

    private function routeNotificationTarget(object $notifiable, string $driver): mixed
    {
        return method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor($driver)
            : null;
    }
}
