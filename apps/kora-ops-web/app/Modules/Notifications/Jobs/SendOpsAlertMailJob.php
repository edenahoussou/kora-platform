<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationDeliveryStatus;
use App\Modules\Notifications\Mail\OpsAlertMail;
use App\Modules\Notifications\Models\OpsNotificationDelivery;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Notifications\Services\NotificationLogRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envoie l'e-mail d'une alerte, une fois les notifications standards
 * publiées : directeurs des opérations de l'agence du convoyeur en
 * destinataires, super-administrateurs en copie. Un seul e-mail par
 * événement ; le résultat est conservé par destinataire (audit). Les
 * livraisons déjà envoyées ne sont jamais renvoyées (retry sans doublon).
 */
class SendOpsAlertMailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        /** @var array<int, int> $backoff */
        $backoff = config('ops_notifications.mail.backoff', [60, 300, 900]);

        return $backoff;
    }

    public function __construct(public readonly int $eventId) {}

    public function handle(): void
    {
        $event = OpsNotificationEvent::query()->with(['departure.line', 'terminal', 'subject'])->find($this->eventId);

        if ($event === null) {
            return;
        }

        $deliveries = $this->pendingDeliveries();

        if ($deliveries->isEmpty()) {
            return;
        }

        $to = $this->addresses($deliveries, 'to');
        $cc = $this->addresses($deliveries, 'cc');

        if ($to === []) {
            return;
        }

        try {
            Mail::to($to)->cc($cc)->send(new OpsAlertMail($event));
        } catch (Throwable $exception) {
            $this->markAll($deliveries, OpsNotificationDeliveryStatus::Failed, Str::limit($exception->getMessage(), 250, ''));

            throw $exception;
        }

        $this->markAll($deliveries, OpsNotificationDeliveryStatus::Sent);
    }

    /**
     * Abandon après la dernière tentative : les livraisons restent `failed`
     * (déjà comptées à chaque tentative dans handle()), avec la cause finale.
     */
    public function failed(Throwable $exception): void
    {
        $this->markAll($this->pendingDeliveries(), OpsNotificationDeliveryStatus::Failed, Str::limit($exception->getMessage(), 250, ''), countAttempt: false);
    }

    /**
     * @return Collection<int, OpsNotificationDelivery>
     */
    private function pendingDeliveries(): Collection
    {
        return OpsNotificationDelivery::query()
            ->with('recipient')
            ->where('event_id', $this->eventId)
            ->where('channel', OpsNotificationChannel::Mail->value)
            ->whereIn('status', [OpsNotificationDeliveryStatus::Pending->value, OpsNotificationDeliveryStatus::Failed->value])
            ->get();
    }

    /**
     * @param  Collection<int, OpsNotificationDelivery>  $deliveries
     * @return list<string>
     */
    private function addresses(Collection $deliveries, string $role): array
    {
        // ✅ CORRECTION : array_values() garantit à PHPStan que le résultat est une "list" (clés 0, 1, 2...)
        return array_values(
            $deliveries
                ->filter(fn (OpsNotificationDelivery $delivery): bool => $delivery->mail_role === $role)
                ->map(fn (OpsNotificationDelivery $delivery): string => $delivery->recipient->email)
                ->unique()
                ->all()
        );
    }

    /**
     * @param  Collection<int, OpsNotificationDelivery>  $deliveries
     */
    private function markAll(Collection $deliveries, OpsNotificationDeliveryStatus $status, ?string $error = null, bool $countAttempt = true): void
    {
        foreach ($deliveries as $delivery) {
            $delivery->forceFill([
                'status' => $status,
                'attempts' => $delivery->attempts + ($countAttempt ? 1 : 0),
                'error' => $error,
                'delivered_at' => $status === OpsNotificationDeliveryStatus::Sent ? now() : null,
            ])->save();

            // Trace d'audit dans `notification_logs` (une ligne par destinataire).
            app(NotificationLogRecorder::class)->recordMail($delivery);
        }
    }
}
