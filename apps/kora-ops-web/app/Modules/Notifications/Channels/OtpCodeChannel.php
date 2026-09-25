<?php

namespace App\Modules\Notifications\Channels;

use App\Models\User;
use App\Modules\Notifications\Services\WhatsApp\WhatsAppMessageDispatcher;
use App\Notifications\IdentityAccess\OtpCodeNotification;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Log;
use Throwable;

class OtpCodeChannel
{
    public function __construct(
        private readonly WhatsAppMessageDispatcher $whatsapp,
        private readonly MailChannel $mail,
    ) {}

    public function send(object $notifiable, OtpCodeNotification $notification): void
    {
        $phone = $notifiable instanceof User ? $notifiable->phone : null;

        if ($phone === null || trim($phone) === '') {
            $this->mail->send($notifiable, $notification);

            return;
        }

        try {
            $this->whatsapp->send(
                $phone,
                $notification->toWhatsApp($notifiable),
            );
        } catch (Throwable $exception) {
            Log::warning('OTP WhatsApp delivery failed; falling back to email.', [
                'exception' => $exception::class,
                'user_id' => $notifiable->getKey(),
            ]);

            $this->mail->send($notifiable, $notification);
        }
    }
}
