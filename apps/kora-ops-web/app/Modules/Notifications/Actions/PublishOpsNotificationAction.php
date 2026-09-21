<?php

namespace App\Modules\Notifications\Actions;

use App\Models\User;
use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationDeliveryStatus;
use App\Modules\Notifications\Data\OpsNotificationDraft;
use App\Modules\Notifications\Events\OpsNotificationPublished;
use App\Modules\Notifications\Http\Resources\OpsNotificationResource;
use App\Modules\Notifications\Jobs\SendOpsAlertMailJob;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationDelivery;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Notifications\Services\OpsNotificationPreferences;
use App\Modules\Notifications\Services\OpsNotificationRecipientResolver;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Throwable;

/**
 * Publie un événement opérationnel, dans cet ordre :
 *
 *  1. persiste l'événement (dédupliqué par `dedupe_key`) ;
 *  2. crée la notification de chaque destinataire (canal `in_app`, qui est la
 *     référence : récupérable par l'API après une coupure ou un redémarrage) ;
 *  3. diffuse en temps réel (canal `realtime`, si un pilote est configuré) ;
 *  4. planifie l'e-mail d'alerte (canal `mail`) — seulement après les
 *     notifications standards.
 *
 * Idempotent : rejouer le même événement (retry) ne crée ni événement, ni
 * notification, ni e-mail en double ; une livraison déjà réussie n'est jamais
 * rétrogradée, une livraison échouée est retentée.
 *
 * Ne lève JAMAIS : une panne de publication ne doit pas faire échouer
 * l'opération métier qui l'a déclenchée (scan, synchronisation…). L'erreur
 * est rapportée aux logs.
 */
class PublishOpsNotificationAction
{
    public function __construct(
        private OpsNotificationRecipientResolver $resolver,
        private OpsNotificationPreferences $preferences,
    ) {}

    public function execute(OpsNotificationDraft $draft): ?OpsNotificationEvent
    {
        try {
            return $this->publish($draft);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function publish(OpsNotificationDraft $draft): OpsNotificationEvent
    {
        $event = $this->findOrCreateEvent($draft);

        $created = [];

        foreach ($this->resolver->inAppRecipients($draft) as $user) {
            if (! $this->preferences->allows($user, $draft->type, OpsNotificationChannel::InApp)) {
                $this->recordDelivery($event, $user, OpsNotificationChannel::InApp, OpsNotificationDeliveryStatus::Skipped, error: 'preference_disabled');

                continue;
            }

            $notification = OpsNotification::query()->firstOrCreate([
                'event_id' => $event->id,
                'user_id' => $user->id,
            ]);

            $this->recordDelivery($event, $user, OpsNotificationChannel::InApp, OpsNotificationDeliveryStatus::Sent, $notification);

            if ($notification->wasRecentlyCreated) {
                $created[] = [$notification, $user];
            }
        }

        foreach ($created as [$notification, $user]) {
            $this->broadcast($event, $notification, $user);
        }

        $this->scheduleMail($event, $draft);

        return $event;
    }

    private function findOrCreateEvent(OpsNotificationDraft $draft): OpsNotificationEvent
    {
        $existing = OpsNotificationEvent::query()->where('dedupe_key', $draft->dedupeKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return OpsNotificationEvent::query()->create([
                'dedupe_key' => $draft->dedupeKey,
                'type' => $draft->type,
                'severity' => $draft->type->severity(),
                'title' => $draft->type->title(),
                'message' => $draft->message,
                'departure_id' => $draft->departure?->id,
                'terminal_p501_id' => $draft->terminal?->id,
                'subject_user_id' => $draft->subject?->id,
                'data' => $draft->data,
                'occurred_at' => $draft->occurredAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Course : un autre traitement a publié le même événement entre-temps.
            return OpsNotificationEvent::query()->where('dedupe_key', $draft->dedupeKey)->firstOrFail();
        }
    }

    private function broadcast(OpsNotificationEvent $event, OpsNotification $notification, User $user): void
    {
        try {
            $event->loadMissing(['departure.line', 'terminal']);
            $notification->setRelation('event', $event);

            Event::dispatch(new OpsNotificationPublished(
                $user->uuid,
                (new OpsNotificationResource($notification))->resolve(request()),
            ));

            $this->recordDelivery($event, $user, OpsNotificationChannel::Realtime, OpsNotificationDeliveryStatus::Sent, $notification);
        } catch (Throwable $exception) {
            // Le temps réel est un plus : la notification reste récupérable par l'API.
            $this->recordDelivery($event, $user, OpsNotificationChannel::Realtime, OpsNotificationDeliveryStatus::Failed, $notification, error: Str::limit($exception->getMessage(), 250, ''));
        }
    }

    private function scheduleMail(OpsNotificationEvent $event, OpsNotificationDraft $draft): void
    {
        if (! $draft->type->isAlert() || ! app(OperationsSettings::class)->notificationsMailEnabled) {
            return;
        }

        $recipients = $this->resolver->mailRecipients($draft);

        foreach (['to' => $recipients['to'], 'cc' => $recipients['cc']] as $role => $users) {
            foreach ($users as $user) {
                OpsNotificationDelivery::query()->firstOrCreate(
                    [
                        'event_id' => $event->id,
                        'recipient_user_id' => $user->id,
                        'channel' => OpsNotificationChannel::Mail->value,
                    ],
                    [
                        'status' => OpsNotificationDeliveryStatus::Pending,
                        'mail_role' => $role,
                        'attempts' => 0,
                    ],
                );
            }
        }

        $hasWork = OpsNotificationDelivery::query()
            ->where('event_id', $event->id)
            ->where('channel', OpsNotificationChannel::Mail->value)
            ->whereIn('status', [OpsNotificationDeliveryStatus::Pending->value, OpsNotificationDeliveryStatus::Failed->value])
            ->exists();

        if ($hasWork) {
            SendOpsAlertMailJob::dispatch($event->id)->afterCommit();
        }
    }

    private function recordDelivery(
        OpsNotificationEvent $event,
        User $user,
        OpsNotificationChannel $channel,
        OpsNotificationDeliveryStatus $status,
        ?OpsNotification $notification = null,
        ?string $error = null,
    ): void {
        $delivery = OpsNotificationDelivery::query()->firstOrNew([
            'event_id' => $event->id,
            'recipient_user_id' => $user->id,
            'channel' => $channel->value,
        ]);

        // Une livraison réussie n'est jamais rétrogradée par un retry.
        if ($delivery->exists && $delivery->status === OpsNotificationDeliveryStatus::Sent) {
            return;
        }

        $delivery->fill([
            'notification_id' => $notification?->id,
            'status' => $status,
            'attempts' => $delivery->attempts + 1,
            'error' => $error,
            'delivered_at' => $status === OpsNotificationDeliveryStatus::Sent ? now() : null,
        ])->save();
    }
}
