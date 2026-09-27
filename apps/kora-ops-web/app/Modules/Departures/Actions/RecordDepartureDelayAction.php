<?php

namespace App\Modules\Departures\Actions;

use App\Models\User;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Events\DepartureDelayRecorded;
use App\Modules\Departures\Models\Departure;
use App\Modules\Notifications\Services\OpsNotifier;
use App\Modules\Notifications\Services\PassengerNotificationPublisher;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDepartureDelayAction
{
    public function __construct(private OpsNotifier $notifier, private PassengerNotificationPublisher $passengerNotifications) {}

    public function handle(Departure $departure, User $actor, int $delayMinutes, string $reason): Departure
    {
        $reason = trim($reason);
        $maxDelayMinutes = max(0, app(OperationsSettings::class)->maxDepartureDelayMinutes);

        if ($delayMinutes < 0 || $delayMinutes > $maxDelayMinutes) {
            throw ValidationException::withMessages([
                'delay_minutes' => "Le retard doit être compris entre 0 et {$maxDelayMinutes} minutes.",
            ]);
        }

        if ($reason === '') {
            throw ValidationException::withMessages([
                'delay_reason' => 'Le motif du retard est obligatoire.',
            ]);
        }

        $updated = DB::transaction(function () use ($departure, $actor, $delayMinutes, $reason): Departure {
            $lockedDeparture = Departure::query()
                ->whereKey($departure->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $status = $lockedDeparture->status;

            if (! in_array($status, [DepartureStatus::Scheduled, DepartureStatus::Boarding], true)) {
                throw ValidationException::withMessages([
                    'departure' => 'Le retard ne peut être enregistré que pour un départ programmé ou en embarquement.',
                ]);
            }

            $previousDelayMinutes = (int) $lockedDeparture->delay_minutes;
            $recordedAt = now()->toImmutable();

            $lockedDeparture->update([
                'delay_minutes' => $delayMinutes,
                'delay_reason' => $reason,
                'delay_recorded_at' => $recordedAt,
                'delay_recorded_by' => $actor->getKey(),
            ]);

            activity('departures')
                ->performedOn($lockedDeparture)
                ->causedBy($actor)
                ->event('delay_recorded')
                ->withProperties([
                    'departure_uuid' => $lockedDeparture->uuid,
                    'previous_delay_minutes' => $previousDelayMinutes,
                    'delay_minutes' => $delayMinutes,
                    'reason' => $reason,
                ])
                ->log('Retard du départ enregistré');

            DepartureDelayRecorded::dispatch(
                departure: $lockedDeparture,
                actor: $actor,
                previousDelayMinutes: $previousDelayMinutes,
                delayMinutes: $delayMinutes,
                reason: $reason,
                recordedAt: $recordedAt,
            );

            return $lockedDeparture->refresh();
        });

        if ($delayMinutes > 0) {
            $this->notifier->departureDelayed($updated, $delayMinutes);
        }

        $settings = app(OperationsSettings::class);
        if ($delayMinutes >= $settings->passengerDelayThresholdMinutes) {
            $eta = $updated->scheduledDepartureAt()->addMinutes($delayMinutes)->toIso8601String();
            $this->passengerNotifications->departureDelayed($updated, $eta);
        }

        if ((int) $departure->delay_minutes !== $delayMinutes) {
            $this->passengerNotifications->scheduleDepartureReminders($updated);
        }

        return $updated;
    }
}
