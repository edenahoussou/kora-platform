<?php

namespace App\Modules\Monitoring\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Spatie\Health\Checks\Result;

/**
 * Alerte « santé dégradée » envoyée au collectif monitoring. Reçoit les
 * résultats en échec d'une exécution de health:check ; l'envoi est
 * throttlé par combinaison de checks fautifs pour éviter le spam tant
 * que la même panne persiste.
 */
class HealthCheckFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<int, Result> $results */
    public function __construct(private readonly array $results) {}

    /** @return array<int, string> */
    public function via(): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        /** @var int $minutes */
        $minutes = config('health.notifications.throttle_notifications_for_minutes', 60);

        if ($minutes === 0) {
            return true;
        }

        $key = 'monitoring:health-alert:'.md5(implode(',', $this->checkNames()));

        return (bool) cache()->add($key, true, now()->addMinutes($minutes));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[KORA OPS] Santé dégradée — '.$this->failureCount().' check'.($this->failureCount() > 1 ? 's' : '').' en échec')
            ->greeting('Alerte santé applicative')
            ->line('Les vérifications suivantes remontent un problème sur '.config('app.name').' ('.config('app.env').') :');

        foreach ($this->results as $result) {
            $mail->line('• '.$result->check->getLabel().' : '.$result->getNotificationMessage());
        }

        return $mail
            ->action('Ouvrir l\'écran Monitoring', route('administration.monitoring'))
            ->line('Cette alerte se répète au plus toutes les heures tant que les mêmes checks restent en échec.')
            ->salutation('KORA OPS — Supervision');
    }

    /**
     * @return array<int, string>
     */
    private function checkNames(): array
    {
        $names = array_map(
            fn (Result $result): string => $result->check->getName(),
            $this->results,
        );
        sort($names);

        return $names;
    }

    private function failureCount(): int
    {
        return count($this->results);
    }
}
