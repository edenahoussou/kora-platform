<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\Parcels\Data\ParcelInspectionCategory;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParcelIntake
{
    public function __construct(
        private ParcelAccess $access,
        private ParcelPricing $pricing,
        private ParcelRouting $routing,
        private ParcelReference $reference,
        private PassengerIdentityResolver $identity,
    ) {}

    /**
     * Resolve a deposit location and destination from either an explicit hub pair
     * or the legacy corridor UUID.
     *
     * @param  array<string, mixed>  $input
     * @return array{deposit: ParcelHub, destination: ParcelHub, corridor: ParcelCorridor}
     */
    public function resolve(array $input): array
    {
        $corridorUuid = $input['corridor_uuid'] ?? null;

        if (is_string($corridorUuid) && $corridorUuid !== '') {
            $corridor = ParcelCorridor::query()
                ->where('uuid', $corridorUuid)
                ->with(['originHub.stop', 'destinationHub.stop'])
                ->firstOrFail();
            $deposit = $corridor->originHub;
            $destination = $corridor->destinationHub;
            abort_if($deposit === null || $destination === null, 409, 'Corridor deposit locations are incomplete.');

            $requestedDeposit = $input['deposit_hub_uuid'] ?? null;
            $requestedDestination = $input['destination_hub_uuid'] ?? null;
            abort_if(is_string($requestedDeposit) && $requestedDeposit !== $deposit->uuid, 409, 'Deposit location does not match the corridor.');
            abort_if(is_string($requestedDestination) && $requestedDestination !== $destination->uuid, 409, 'Destination location does not match the corridor.');

            return ['deposit' => $deposit, 'destination' => $destination, 'corridor' => $corridor];
        }

        $deposit = ParcelHub::query()
            ->where('uuid', (string) ($input['deposit_hub_uuid'] ?? ''))
            ->with('stop')
            ->firstOrFail();
        $destination = ParcelHub::query()
            ->where('uuid', (string) ($input['destination_hub_uuid'] ?? ''))
            ->with('stop')
            ->firstOrFail();
        $corridor = ParcelCorridor::query()
            ->where('origin_hub_id', $deposit->id)
            ->where('destination_hub_id', $destination->id)
            ->with(['originHub.stop', 'destinationHub.stop'])
            ->first();
        abort_if($corridor === null, 409, 'No parcel corridor connects this deposit location to the requested destination.');

        return ['deposit' => $deposit, 'destination' => $destination, 'corridor' => $corridor];
    }

    /** @param array<string, mixed> $input */
    public function create(ParcelCorridor $corridor, User $actor, array $input, bool $counter): Parcel
    {
        $corridor->loadMissing(['originHub', 'destinationHub']);
        $deposit = $corridor->originHub;
        $destination = $corridor->destinationHub;
        abort_if($deposit === null || $destination === null, 409, 'Corridor deposit locations are incomplete.');

        return $this->createForDeposit($deposit, $destination, $actor, $input, $counter);
    }

    /** @param array<string, mixed> $input */
    public function createForDeposit(ParcelHub $depositHub, ParcelHub $destinationHub, User $actor, array $input, bool $counter): Parcel
    {
        return DB::transaction(function () use ($depositHub, $destinationHub, $actor, $input, $counter): Parcel {
            $corridor = ParcelCorridor::query()
                ->where('origin_hub_id', $depositHub->id)
                ->where('destination_hub_id', $destinationHub->id)
                ->lockForUpdate()
                ->first();
            abort_if($corridor === null, 409, 'No parcel corridor connects this deposit location to the requested destination.');

            if ($counter) {
                $this->access->atHub($actor, $depositHub, 'parcels.intake');
            } else {
                $this->access->require($actor, 'parcels.customer');
            }

            $this->pricing->ensureOpen($corridor);
            abort_if(! $depositHub->isActive(), 409, 'Deposit location is not open for parcel intake.');
            abort_if(! $destinationHub->isActive(), 409, 'Destination location is not open for parcel intake.');
            $this->routing->assertDepositMatchesCorridor($depositHub, $corridor);
            $this->routing->assertOperationallyAvailable($depositHub, $destinationHub, (int) $input['weight_grams']);

            $this->pricing->calculate($corridor, (int) $input['weight_grams']);
            $senderPhone = $this->normalize($counter ? (string) ($input['sender_phone'] ?? '') : (string) $actor->phone, 'sender_phone');
            $recipientPhone = $this->normalize((string) $input['recipient_phone'], 'recipient_phone');
            $sender = $counter ? User::query()->where('phone', $senderPhone)->first() : $actor;
            if ($counter && $sender !== null && (! $sender->can('parcels.customer') || ! $this->access->hasVerifiedPhone($sender))) {
                $sender = null;
            }

            $fingerprint = $this->fingerprint($depositHub, $destinationHub, $input, $senderPhone, $recipientPhone);
            $attributes = [
                'corridor_id' => $corridor->id,
                'deposit_hub_id' => $depositHub->id,
                'selling_agency_id' => $depositHub->agency_id,
                'sender_user_id' => $sender?->id,
                'status' => 'preregistered',
                'sender_name' => $input['sender_name'],
                'sender_phone' => $senderPhone,
                'recipient_name' => $input['recipient_name'],
                'recipient_phone' => $recipientPhone,
                'recipient_phone_hash' => ParcelAccess::phoneHash($recipientPhone),
                'contents' => $input['contents'],
                'declared_grams' => $input['weight_grams'],
                'intake_fingerprint' => $fingerprint,
                'expires_at' => now()->addHours(app(OperationsSettings::class)->parcelIntakeExpiryHours),
            ];

            $idempotencyKey = $input['idempotency_key'] ?? null;
            abort_unless(is_string($idempotencyKey) && $idempotencyKey !== '', 422, 'An idempotency key is required.');

            $parcel = Parcel::query()->createOrFirst(
                ['idempotency_key' => $idempotencyKey, 'selling_agency_id' => $depositHub->agency_id],
                $attributes,
            );

            if (! $parcel->wasRecentlyCreated) {
                $stored = $parcel->intake_fingerprint;
                abort_unless(is_string($stored) && hash_equals($stored, $fingerprint), 409,
                    'This idempotency key was already used with different intake data.');

                return $parcel;
            }

            $this->reference->assign($parcel);
            $parcel->record('preregistered', $actor, $depositHub, channel: $counter ? 'ops' : 'customer');

            return $parcel;
        });
    }

    /** @param array<string, mixed> $input */
    public function inspect(Parcel $parcel, User $actor, array $input): Parcel
    {
        return DB::transaction(function () use ($parcel, $actor, $input): Parcel {
            $parcel = Parcel::query()
                ->with(['corridor.originHub', 'depositHub'])
                ->lockForUpdate()
                ->findOrFail($parcel->id);
            $corridor = $parcel->corridor;
            $hub = $parcel->depositHub ?? $corridor?->originHub;
            abort_if($corridor === null || $hub === null, 409, 'Parcel deposit location is unavailable.');

            $this->access->atHub($actor, $hub, 'parcels.intake');
            abort_unless(
                in_array($parcel->status, ['preregistered', 'weighed'], true) && $parcel->expires_at->isFuture(),
                409,
                'Parcel is not open for inspection.',
            );
            abort_unless($hub->isActive(), 409, 'Deposit location is not open for inspection.');
            abort_unless($hub->supports_weighing, 409, 'Deposit location has no approved weighing equipment.');

            $this->pricing->ensureOpen($corridor);

            $settings = app(OperationsSettings::class);
            $category = ParcelInspectionCategory::from((string) $input['category']);
            $weight = isset($input['weight_grams']) ? (int) $input['weight_grams'] : null;
            $cashValue = (int) ($input['cash_value'] ?? 0);
            $previousWeight = $parcel->weight_grams;
            $previousCategory = $parcel->inspection_category?->value;
            $previousCharge = $parcel->charges()->whereNull('superseded_at')->latest('id')->first();

            $reasonCode = null;
            $reason = trim((string) ($input['reason'] ?? ''));

            if ($category->isForbidden()) {
                $reasonCode = 'forbidden_category';
                $reason = $reason !== '' ? $reason : "Forbidden category: {$category->value}.";
            } elseif ($category->requiresCashDeclaration() && $cashValue > $settings->parcelCashDeclarationThresholdFcfa) {
                $reasonCode = 'cash_threshold_exceeded';
                $reason = $reason !== '' ? $reason : 'Cash value exceeds the allowed transport threshold.';
            } elseif ($weight !== null && $weight > $settings->parcelMaximumWeightGrams) {
                $reasonCode = 'weight_above_limit';
                $reason = $reason !== '' ? $reason : "Weight exceeds the {$settings->parcelMaximumWeightGrams} g operational limit.";
            } elseif ($weight !== null && $weight < $settings->parcelMinimumWeightGrams) {
                $reasonCode = 'weight_below_limit';
                $reason = $reason !== '' ? $reason : "Weight is below the {$settings->parcelMinimumWeightGrams} g operational minimum.";
            }

            if ($reasonCode === null && $weight === null) {
                throw ValidationException::withMessages(['weight_grams' => 'The measured weight is required to accept a parcel.']);
            }

            $parcel->charges()->whereNull('superseded_at')->update(['superseded_at' => now()]);

            if ($reasonCode !== null) {
                $parcel->update([
                    'status' => 'refused',
                    'weight_grams' => $weight ?? $previousWeight,
                    'inspection_category' => $category,
                    'inspection_refusal_reason' => $reason,
                    'inspected_at' => now(),
                    'inspection_hub_id' => $hub->id,
                ]);
                $parcel->record('refused', $actor, $hub, details: [
                    'category' => $category->value,
                    'reason_code' => $reasonCode,
                    'reason' => $reason,
                    'weight_grams' => $weight ?? $previousWeight,
                    'cash_value' => $category->requiresCashDeclaration() ? $cashValue : null,
                    'previous_weight_grams' => $previousWeight,
                    'previous_category' => $previousCategory,
                    'superseded_charge_uuid' => $previousCharge?->uuid,
                    'superseded_charge_total' => $previousCharge?->total,
                ]);

                return $parcel;
            }

            $charge = $parcel->charges()->create($this->pricing->calculate($corridor, $weight));
            $parcel->update([
                'status' => 'weighed',
                'weight_grams' => $weight,
                'inspection_category' => $category,
                'inspection_refusal_reason' => null,
                'inspected_at' => now(),
                'inspection_hub_id' => $hub->id,
            ]);
            $parcel->record('weighed', $actor, $hub, details: [
                'weight_grams' => $weight,
                'category' => $category->value,
                'cash_value' => $category->requiresCashDeclaration() ? $cashValue : null,
                'previous_weight_grams' => $previousWeight,
                'previous_category' => $previousCategory,
                'corrected' => $previousWeight !== null,
                'previous_charge_uuid' => $previousCharge?->uuid,
                'previous_charge_total' => $previousCharge?->total,
                'charge_uuid' => $charge->uuid,
                'charge_total' => $charge->total,
            ]);

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
            abort_unless(in_array($parcel->status, ['preregistered', 'weighed'], true), 409, 'Le colis ne peut pas être annulé dans son statut actuel.');
            $parcel->update(['status' => 'cancelled']);
            $parcel->record('cancelled', $actor);

            return $parcel;
        });
    }

    /** @param array<string, mixed> $input */
    private function fingerprint(ParcelHub $depositHub, ParcelHub $destinationHub, array $input, string $senderPhone, string $recipientPhone): string
    {
        $payload = [
            'deposit_hub_id' => $depositHub->id,
            'destination_hub_id' => $destinationHub->id,
            'sender_name' => trim((string) $input['sender_name']),
            'sender_phone' => $senderPhone,
            'recipient_name' => trim((string) $input['recipient_name']),
            'recipient_phone' => $recipientPhone,
            'contents' => trim((string) $input['contents']),
            'weight_grams' => (int) $input['weight_grams'],
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function normalize(string $phone, string $field): string
    {
        return $this->identity->normalizePhone($phone)
            ?? throw ValidationException::withMessages([$field => 'Invalid phone number.']);
    }
}
