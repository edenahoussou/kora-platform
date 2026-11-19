<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelOutbox;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ParcelHandoff
{
    public function __construct(
        private ParcelAccess $access,
        private ParcelTransport $transport,
        private ParcelPricing $pricing,
        private ParcelNotifications $notifications,
    ) {}

    public function receive(Parcel $parcel, User $actor): Parcel
    {
        return DB::transaction(function () use ($parcel, $actor): Parcel {
            $parcel = $this->transport->lock($parcel);
            $hub = $parcel->custodyHub;
            abort_unless($hub !== null && in_array($parcel->status, ['arrived', 'return_arrived'], true), 409);
            $this->access->atHub($actor, $hub, 'parcels.handoff');
            $return = $parcel->status === 'return_arrived';
            $settings = app(OperationsSettings::class);
            $parcel->assignments()->whereNull('released_at')->whereNotNull('unloaded_at')->update(['released_at' => now()]);
            if ($return) {
                $parcel->update(['status' => 'returned']);
            } else {
                $parcel->update(['status' => 'available', 'available_at' => now(), 'return_due_at' => now()->addDays($settings->parcelReturnDays)]);
                foreach ([$settings->parcelFirstReminderDays, $settings->parcelSecondReminderDays] as $days) {
                    $this->notifications->enqueue($parcel, "reminder:{$days}", 'reminder', $parcel->recipient_phone,
                        "Le colis {$parcel->reference} vous attend à son hub de destination.", now()->addDays($days));
                }
            }
            $this->issueCode($parcel, $return);
            $parcel->record($parcel->status, $actor, $hub);

            return $parcel;
        });
    }

    public function reissue(Parcel $parcel, User $actor): Parcel
    {
        return DB::transaction(function () use ($parcel, $actor): Parcel {
            $parcel = $this->transport->lock($parcel);
            abort_unless($parcel->custodyHub !== null && $this->awaitsHandoff($parcel), 409);
            $this->access->atHub($actor, $parcel->custodyHub, 'parcels.override');
            $this->issueCode($parcel, $parcel->status === 'returned');
            $parcel->record('handoff_code_reissued', $actor, $parcel->custodyHub);

            return $parcel;
        });
    }

    /** @param array<string, mixed> $input */
    public function release(Parcel $parcel, User $actor, array $input): Parcel
    {
        $result = DB::transaction(function () use ($parcel, $actor, $input): ?Parcel {
            $parcel = $this->transport->lock($parcel);
            $hub = $parcel->custodyHub;
            abort_unless($hub !== null && $this->awaitsHandoff($parcel), 409);
            $this->access->atHub($actor, $hub, 'parcels.handoff');
            $return = $parcel->status === 'returned';
            if ($return) {
                abort_unless($parcel->charges()->where('return_leg', true)->whereNotNull('settled_payment_id')->exists(), 409, 'Return fees remain unpaid.');
            }

            $details = [];
            if (! empty($input['identity_type'])) {
                $this->access->atHub($actor, $hub, 'parcels.override');
                abort_if(blank($input['reason'] ?? null), 422);
                $details = ['identity_type' => $input['identity_type'], 'reason' => $input['reason']];
            } else {
                abort_if($parcel->handoff_attempts >= app(OperationsSettings::class)->parcelHandoffMaxAttempts, 429);
                if ($parcel->handoff_hash === null || ! Hash::check((string) ($input['code'] ?? ''), $parcel->handoff_hash)) {
                    $parcel->increment('handoff_attempts');
                    $parcel->record('handoff_code_rejected', $actor, $hub);

                    return null;
                }
            }

            $parcel->update([
                'status' => $return ? 'returned' : 'withdrawn',
                'return_handed_over_at' => $return ? now() : null,
                'handoff_hash' => null,
                'custody_hub_id' => null,
            ]);
            $parcel->record($return ? 'return_handed_over' : 'withdrawn', $actor, $hub, details: $details);
            if (! $return) {
                $this->notifications->milestone($parcel, 'withdrawn');
            }

            return $parcel;
        });
        abort_if($result === null, 422, 'Code de retrait incorrect.');

        return $result;
    }

    public function advanceDueParcels(): void
    {
        Parcel::query()->whereIn('status', ['preregistered', 'weighed'])->where('expires_at', '<=', now())
            ->eachById(function (Parcel $parcel): void {
                DB::transaction(function () use ($parcel): void {
                    $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
                    if (in_array($parcel->status, ['preregistered', 'weighed'], true) && $parcel->expires_at->isPast()) {
                        $parcel->update(['status' => 'expired']);
                        $parcel->record('expired', channel: 'system');
                    }
                });
            });
        Parcel::query()->where('status', 'available')->where('return_due_at', '<=', now())
            ->eachById(function (Parcel $parcel): void {
                DB::transaction(function () use ($parcel): void {
                    $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
                    if ($parcel->status !== 'available' || $parcel->incidents()->where('blocking', true)->whereNull('resolved_at')->exists()) {
                        return;
                    }
                    $parcel->update(['status' => 'return_pending', 'handoff_hash' => null]);
                    $parcel->record('return_pending', hub: $parcel->custodyHub, channel: 'system');
                    $this->notifications->milestone($parcel, 'return_pending');
                });
            });
    }

    public function prepareReturnCharge(Parcel $parcel, User $actor): void
    {
        DB::transaction(function () use ($parcel, $actor): void {
            $parcel = $this->transport->lock($parcel);
            $hub = $parcel->corridor->originHub;
            $this->access->atHub($actor, $hub, 'parcels.intake');
            abort_unless($parcel->status === 'returned' && $this->awaitsHandoff($parcel), 409);
            if (! $parcel->charges()->where('return_leg', true)->exists()) {
                $parcel->charges()->create($this->pricing->calculate($parcel->corridor, $parcel->weight_grams, true) + ['return_leg' => true]);
                $parcel->record('return_charge_created', $actor, $hub);
            }
        });
    }

    private function awaitsHandoff(Parcel $parcel): bool
    {
        return ($parcel->status === 'available' && $parcel->return_due_at?->isFuture() === true)
            || ($parcel->status === 'returned' && $parcel->return_handed_over_at === null);
    }

    private function issueCode(Parcel $parcel, bool $return): void
    {
        $kind = $return ? 'return_code' : 'withdrawal_code';
        ParcelOutbox::query()->where('parcel_id', $parcel->id)->where('kind', $kind)->whereNull('completed_at')->update(['completed_at' => now()]);
        $code = (string) random_int(100000, 999999);
        $parcel->update(['handoff_hash' => Hash::make($code), 'handoff_attempts' => 0]);
        $this->notifications->enqueue($parcel, $kind.':'.Str::uuid(), $kind, $return ? $parcel->sender_phone : $parcel->recipient_phone,
            "Code de retrait du colis {$parcel->reference} : {$code}.");
    }
}
