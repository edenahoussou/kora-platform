<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationDeliveryStatus;
use App\Modules\Notifications\Models\NotificationLog;
use App\Modules\Notifications\Models\OpsNotificationDelivery;
use Throwable;

/**
 * Écrit dans `notification_logs` le résultat de l'envoi d'un e-mail d'alerte
 * pour UN destinataire. Une ligne par (événement, destinataire) : chaque
 * nouvelle tentative met la ligne à jour au lieu d'en créer une autre.
 *
 * `payload` ne contient que le type d'événement, la gravité, le rôle To/Cc,
 * le nombre de tentatives et la cause d'un échec — jamais de donnée
 * financière ni de contenu de message.
 *
 * Ne lève jamais : un journal indisponible ne doit pas empêcher (ni faire
 * rejouer) un envoi d'e-mail. `ops_notification_deliveries` reste la source
 * de vérité des relances ; ce journal est la trace d'audit.
 */
class NotificationLogRecorder
{
    public const string MAIL_TEMPLATE = 'ops_alert';

    public const string SMS_CHANNEL = 'sms';

    public function recordMail(OpsNotificationDelivery $delivery): void
    {
        try {
            $event = $delivery->event;
            $recipient = $delivery->recipient;

            if ($recipient->email === '') {
                return;
            }

            NotificationLog::query()->updateOrCreate(
                [
                    'reference_type' => $event->getMorphClass(),
                    'reference_id' => $event->getKey(),
                    'channel' => OpsNotificationChannel::Mail->value,
                    'recipient' => $recipient->email,
                    'template' => self::MAIL_TEMPLATE,
                ],
                [
                    'status' => $delivery->status->value,
                    'sent_at' => $delivery->status === OpsNotificationDeliveryStatus::Sent ? ($delivery->delivered_at ?? now()) : null,
                    'payload' => array_filter([
                        'type' => $event->type->value,
                        'severity' => $event->severity->value,
                        'mail_role' => $delivery->mail_role,
                        'attempts' => $delivery->attempts,
                        'error' => $delivery->error,
                    ], static fn (mixed $value): bool => $value !== null),
                ],
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Journalise un SMS transactionnel ; ne lève jamais.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordSms(
        string $recipient,
        string $template,
        string $status,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $payload = [],
    ): void {
        try {
            $values = [
                'status' => $status,
                'sent_at' => $status === 'sent' ? now() : null,
                'payload' => array_filter($payload, static fn (mixed $value): bool => $value !== null),
            ];
            $key = [
                'channel' => self::SMS_CHANNEL,
                'recipient' => $recipient,
                'template' => $template,
            ];

            if ($referenceType !== null && $referenceId !== null) {
                NotificationLog::query()->updateOrCreate(
                    [...$key, 'reference_type' => $referenceType, 'reference_id' => $referenceId],
                    $values,
                );

                return;
            }

            NotificationLog::query()->create([...$key, ...$values]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
