<?php

namespace App\Modules\Notifications\Channels;

use App\Models\User;
use App\Modules\IdentityAccess\Exceptions\OtpDeliveryException;
use App\Modules\Notifications\Services\NotificationLogRecorder;
use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;
use App\Modules\Notifications\Services\WhatsApp\WhatsAppMessageDispatcher;
use App\Notifications\IdentityAccess\OtpCodeNotification;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Log;
use Throwable;

class OtpCodeChannel
{
    /**
     * Domaine des emails de convenance créés pour les comptes
     * téléphone-only (réservation assistée, inscription par numéro) :
     * aucun courrier n'y aboutira jamais, un « repli email » vers cette
     * adresse serait une perte silencieuse du code.
     */
    public const PLACEHOLDER_EMAIL_DOMAIN = 'accounts.kora.invalid';

    public function __construct(
        private readonly WhatsAppMessageDispatcher $whatsapp,
        private readonly MailChannel $mail,
        private readonly ConexteoSmsClient $sms,
        private readonly NotificationLogRecorder $recorder,
    ) {}

    public function send(object $notifiable, OtpCodeNotification $notification): void
    {
        $startedAt = microtime(true);
        $context = [
            'challenge_uuid' => $notification->challengeUuid,
            'queue_wait_ms' => $notification->queuedAt === null
                ? null
                : (int) round(max(0, $startedAt - $notification->queuedAt) * 1000),
        ];
        Log::info('OTP delivery started.', $context);

        $phone = $notifiable instanceof User
            ? $notifiable->phone
            : ($this->routeNotificationTarget($notifiable, 'sms')
                ?? $this->routeNotificationTarget($notifiable, 'whatsapp'));

        $smsError = null;

        if ($this->sms->isConfigured() && is_string($phone) && trim($phone) !== '') {
            try {
                $result = $this->sms->send($phone, $notification->toSms($notifiable));
                $this->recorder->recordSms($phone, 'otp', 'sent', payload: [
                    'message_id' => $result['message_id'],
                    'credits_used' => $result['credits_used'],
                    'duplicate' => $result['duplicate'],
                ]);

                return;
            } catch (Throwable $exception) {
                $smsError = $exception;
                $this->recorder->recordSms($phone, 'otp', 'failed', payload: [
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $whatsappError = null;

        if (is_string($phone) && trim($phone) !== '') {
            try {
                $this->whatsapp->send(
                    $phone,
                    $notification->toWhatsApp($notifiable),
                );

                return;
            } catch (Throwable $exception) {
                $whatsappError = $exception;
            }
        }

        if (self::isContactableEmail($this->routeNotificationTarget($notifiable, 'mail'))) {
            if ($smsError !== null || $whatsappError !== null) {
                Log::warning('OTP SMS/WhatsApp delivery failed; falling back to email.', [
                    'exception' => ($smsError ?? $whatsappError)::class,
                    'user_id' => $notifiable instanceof User ? $notifiable->getKey() : null,
                ]);
            }

            $mailStartedAt = microtime(true);
            $mailContext = $context + [
                'before_mail_ms' => (int) round(($mailStartedAt - $startedAt) * 1000),
            ];

            try {
                $sent = $this->mail->send($notifiable, $notification);
            } catch (Throwable $exception) {
                Log::warning('OTP mail transport failed.', $mailContext + [
                    'transport_ms' => (int) round((microtime(true) - $mailStartedAt) * 1000),
                    'exception' => $exception::class,
                ]);

                throw $exception;
            }

            Log::info('OTP mail transport finished.', $mailContext + [
                'transport_ms' => (int) round((microtime(true) - $mailStartedAt) * 1000),
                'accepted' => $sent !== null,
            ]);

            return;
        }

        throw new OtpDeliveryException(previous: $smsError ?? $whatsappError);
    }

    /**
     * Route de livraison déclarée par le notifiable (User ou
     * AnonymousNotifiable) pour un canal donné ; null si le notifiable
     * n'expose pas de routage.
     */
    private function routeNotificationTarget(object $notifiable, string $driver): mixed
    {
        return method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor($driver)
            : null;
    }

    /**
     * Une adresse vers laquelle un code OTP peut réellement aboutir :
     * valide, et pas un placeholder `.invalid` des comptes sans email.
     */
    public static function isContactableEmail(?string $email): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);

        return $domain !== '' && $domain !== self::PLACEHOLDER_EMAIL_DOMAIN;
    }
}
