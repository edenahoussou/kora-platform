<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParcelIntake
{
    public function __construct(
        private ParcelAccess $access,
        private ParcelPricing $pricing,
        private PassengerIdentityResolver $identity,
    ) {}

    /** @param array<string, mixed> $input */
    public function create(ParcelCorridor $corridor, User $actor, array $input, bool $counter): Parcel
    {
        return DB::transaction(function () use ($corridor, $actor, $input, $counter): Parcel {
            $corridor = ParcelCorridor::query()->lockForUpdate()->findOrFail($corridor->id);
            $this->pricing->ensureOpen($corridor);
            if ($counter) {
                $this->access->atHub($actor, $corridor->originHub, 'parcels.intake');
            } else {
                $this->access->require($actor, 'parcels.customer');
            }

            $this->pricing->calculate($corridor, (int) $input['weight_grams']);
            $senderPhone = $this->normalize($counter ? (string) ($input['sender_phone'] ?? '') : (string) $actor->phone, 'sender_phone');
            $recipientPhone = $this->normalize((string) $input['recipient_phone'], 'recipient_phone');
            $sender = $counter ? User::query()->where('phone', $senderPhone)->first() : $actor;
            if ($counter && $sender !== null && (! $sender->can('parcels.customer') || ! $this->access->hasVerifiedPhone($sender))) {
                $sender = null;
            }
            $parcel = Parcel::query()->create([
                'corridor_id' => $corridor->id,
                'sender_user_id' => $sender?->id,
                'status' => 'preregistered',
                'sender_name' => $input['sender_name'],
                'sender_phone' => $senderPhone,
                'recipient_name' => $input['recipient_name'],
                'recipient_phone' => $recipientPhone,
                'recipient_phone_hash' => ParcelAccess::phoneHash($recipientPhone),
                'contents' => $input['contents'],
                'declared_grams' => $input['weight_grams'],
                'expires_at' => now()->addHours(app(OperationsSettings::class)->parcelIntakeExpiryHours),
            ]);
            $parcel->record('preregistered', $actor, channel: $counter ? 'ops' : 'customer');

            return $parcel;
        });
    }

    /** @param array<string, mixed> $input */
    public function inspect(Parcel $parcel, User $actor, array $input): Parcel
    {
        return DB::transaction(function () use ($parcel, $actor, $input): Parcel {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $corridor = $parcel->corridor;
            $hub = $corridor->originHub;
            $this->access->atHub($actor, $hub, 'parcels.intake');
            abort_unless(in_array($parcel->status, ['preregistered', 'weighed'], true) && $parcel->expires_at->isFuture(), 409);

            $this->pricing->ensureOpen($corridor);
            $category = $input['category'];
            $refused = in_array($category, ['flammable', 'chemical', 'undeclared_medicine', 'weapon', 'refused'], true)
                || ($category === 'cash' && (int) $input['cash_value'] > 500000);
            $parcel->charges()->whereNull('superseded_at')->update(['superseded_at' => now()]);
            if ($refused) {
                $parcel->update(['status' => 'refused']);
                $parcel->record('refused', $actor, $hub, details: ['category' => $category, 'reason' => $input['reason'] ?? null]);

                return $parcel;
            }

            $grams = (int) $input['weight_grams'];
            $parcel->charges()->create($this->pricing->calculate($corridor, $grams));
            $parcel->update(['weight_grams' => $grams, 'status' => 'weighed']);
            $parcel->record('weighed', $actor, $hub, details: ['weight_grams' => $grams, 'category' => $category]);

            return $parcel;
        });
    }

    public function cancel(Parcel $parcel, User $actor, bool $counter): Parcel
    {
        return DB::transaction(function () use ($parcel, $actor, $counter): Parcel {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            if ($counter) {
                $this->access->atHub($actor, $parcel->corridor->originHub, 'parcels.intake');
            } else {
                abort_unless($parcel->sender_user_id === $actor->id, 404);
            }
            abort_unless(in_array($parcel->status, ['preregistered', 'weighed'], true), 409);
            $parcel->update(['status' => 'cancelled']);
            $parcel->record('cancelled', $actor);

            return $parcel;
        });
    }

    private function normalize(string $phone, string $field): string
    {
        return $this->identity->normalizePhone($phone)
            ?? throw ValidationException::withMessages([$field => 'Numéro de téléphone invalide.']);
    }
}
