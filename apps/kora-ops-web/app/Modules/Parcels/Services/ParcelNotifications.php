<?php

namespace App\Modules\Parcels\Services;

use App\Modules\Notifications\Services\OpsNotifier;
use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;
use App\Modules\Notifications\Services\WhatsApp\WhatsAppMessageDispatcher;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelOutbox;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ParcelNotifications
{
    private const CLAIM_TTL_MINUTES = 15;

    private const RETRY_DELAY_MINUTES = 15;

    public function milestone(Parcel $parcel, string $kind): void
    {
        [$phone, $message] = match ($kind) {
            'registered' => [$parcel->sender_phone, "Votre colis {$parcel->reference} est enregistré."],
            'ready' => [$parcel->sender_phone, "Votre colis {$parcel->reference} est prêt à expédier."],
            'in_transit' => [$parcel->recipient_phone, "Votre colis {$parcel->reference} est en transit."],
            'arrived' => [$parcel->recipient_phone, "Votre colis {$parcel->reference} est arrivé. Attendez le code de retrait."],
            'withdrawn' => [$parcel->sender_phone, "Votre colis {$parcel->reference} a été retiré."],
            'return_pending' => [$parcel->sender_phone, "Le retour de votre colis {$parcel->reference} est en préparation."],
            default => throw new \InvalidArgumentException("Unknown parcel milestone: {$kind}."),
        };

        $this->enqueue($parcel, $kind, $kind, $phone, $message);
    }

    public function enqueue(Parcel $parcel, string $key, string $kind, string $phone, string $message, ?CarbonInterface $dueAt = null): void
    {
        // La clé unique porte le cycle de vie complet : un rejeu ne crée jamais
        // une seconde ligne d'outbox ni un second envoi.
        ParcelOutbox::query()->createOrFirst(['key' => $parcel->uuid.':'.$key], [
            'parcel_id' => $parcel->id,
            'kind' => $kind,
            'payload' => ['phone' => $phone, 'message' => $message],
            'due_at' => $dueAt ?? now(),
        ]);
    }

    public function incident(Parcel $parcel, string $incidentUuid, bool $blocking = false): void
    {
        $this->enqueue($parcel, 'incident:'.$incidentUuid, 'incident', $parcel->sender_phone,
            "Un incident est en cours de traitement pour le colis {$parcel->reference}.");
        DB::afterCommit(fn () => app(OpsNotifier::class)->parcelAlert($parcel, $incidentUuid, false, ['blocking' => $blocking]));
    }

    public function sendPending(WhatsAppMessageDispatcher $whatsApp, ConexteoSmsClient $sms): void
    {
        $staleBefore = now()->subMinutes(self::CLAIM_TTL_MINUTES);

        ParcelOutbox::query()
            ->whereNull('completed_at')
            ->where('due_at', '<=', now())
            ->where(function ($query) use ($staleBefore): void {
                $query->whereNull('claimed_at')->orWhere('claimed_at', '<=', $staleBefore);
            })
            ->with('parcel')
            ->eachById(function (ParcelOutbox $candidate) use ($whatsApp, $sms): void {
                $message = $this->claim($candidate);
                if ($message === null) {
                    return;
                }

                $message->loadMissing('parcel');
                if ($this->statusChanged($message)) {
                    $this->complete($message, 'skipped');

                    return;
                }

                $this->deliver($message, $whatsApp, $sms);
            });
    }

    private function claim(ParcelOutbox $candidate): ?ParcelOutbox
    {
        return DB::transaction(function () use ($candidate): ?ParcelOutbox {
            $message = ParcelOutbox::query()->whereKey($candidate->getKey())->lockForUpdate()->first();
            if ($message === null || $message->completed_at !== null || $message->due_at->isFuture()) {
                return null;
            }

            if ($message->claimed_at !== null && $message->claimed_at->gt(now()->subMinutes(self::CLAIM_TTL_MINUTES))) {
                return null;
            }

            $message->forceFill([
                'claimed_at' => now(),
                'claim_token' => (string) Str::uuid(),
            ])->save();

            return $message;
        });
    }

    private function statusChanged(ParcelOutbox $message): bool
    {
        $expectedStatus = match ($message->kind) {
            'reminder', 'withdrawal_code' => 'available',
            'return_code' => 'returned',
            default => null,
        };

        return $expectedStatus !== null && $message->parcel?->status !== $expectedStatus;
    }

    private function deliver(ParcelOutbox $message, WhatsAppMessageDispatcher $whatsApp, ConexteoSmsClient $sms): void
    {
        $payload = $message->payload;

        try {
            try {
                $result = $whatsApp->send($payload['phone'], $payload['message']);
                $channel = 'whatsapp';
            } catch (Throwable) {
                $result = $sms->send($payload['phone'], $payload['message'], $message->uuid);
                $channel = 'sms';
            }
        } catch (Throwable) {
            $this->fail($message);

            return;
        }

        $this->complete($message, $channel, $result['message_id'] ?? null);
    }

    private function complete(ParcelOutbox $message, string $channel, ?string $providerMessageId = null): void
    {
        $token = $message->claim_token;

        DB::transaction(function () use ($message, $token, $channel, $providerMessageId): void {
            $row = ParcelOutbox::query()
                ->whereKey($message->getKey())
                ->where('claim_token', $token)
                ->lockForUpdate()
                ->first();

            if ($row === null || $row->completed_at !== null) {
                return;
            }

            $row->forceFill([
                'completed_at' => now(),
                'claimed_at' => null,
                'claim_token' => null,
                'last_error_code' => null,
                'delivered_channel' => $channel,
                'provider_message_id' => $providerMessageId,
            ])->save();
        });
    }

    private function fail(ParcelOutbox $message): void
    {
        $token = $message->claim_token;

        DB::transaction(function () use ($message, $token): void {
            $row = ParcelOutbox::query()
                ->whereKey($message->getKey())
                ->where('claim_token', $token)
                ->lockForUpdate()
                ->first();

            if ($row === null || $row->completed_at !== null) {
                return;
            }

            // Aucun message d'erreur fournisseur n'est conservé : il peut
            // contenir un numéro, un contenu ou un secret de retrait.
            $row->forceFill([
                'claimed_at' => null,
                'claim_token' => null,
                'attempts' => $row->attempts + 1,
                'due_at' => now()->addMinutes(self::RETRY_DELAY_MINUTES),
                'last_error_code' => 'delivery_failed',
            ])->save();
        });
    }
}
