<?php

namespace App\Modules\Parcels\Services;

use App\Modules\Notifications\Services\OpsNotifier;
use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;
use App\Modules\Notifications\Services\WhatsApp\WhatsAppMessageDispatcher;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelOutbox;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class ParcelNotifications
{
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
        ParcelOutbox::query()->firstOrCreate(['key' => $parcel->uuid.':'.$key], [
            'parcel_id' => $parcel->id,
            'kind' => $kind,
            'payload' => ['phone' => $phone, 'message' => $message],
            'due_at' => $dueAt ?? now(),
        ]);
    }

    public function incident(Parcel $parcel, string $incidentUuid): void
    {
        $this->enqueue($parcel, 'incident:'.$incidentUuid, 'incident', $parcel->sender_phone,
            "Un incident est en cours de traitement pour le colis {$parcel->reference}.");
        DB::afterCommit(fn () => app(OpsNotifier::class)->parcelAlert($parcel, $incidentUuid, false));
    }

    public function sendPending(WhatsAppMessageDispatcher $whatsApp, ConexteoSmsClient $sms): void
    {
        ParcelOutbox::query()->whereNull('completed_at')->where('due_at', '<=', now())->with('parcel')->eachById(function (ParcelOutbox $message) use ($whatsApp, $sms): void {
            $expectedStatus = match ($message->kind) {
                'reminder', 'withdrawal_code' => 'available',
                'return_code' => 'returned',
                default => null,
            };
            if ($expectedStatus !== null && $message->parcel?->status !== $expectedStatus) {
                $message->update(['completed_at' => now()]);

                return;
            }

            $payload = $message->payload;
            try {
                try {
                    $whatsApp->send($payload['phone'], $payload['message']);
                } catch (Throwable) {
                    $sms->send($payload['phone'], $payload['message'], $message->uuid);
                }
                $message->update(['completed_at' => now()]);
            } catch (Throwable) {
                $message->increment('attempts', 1, ['due_at' => now()->addMinutes(15)]);
            }
        });
    }
}
