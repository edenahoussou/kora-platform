<?php

namespace App\Modules\FleetLite\Actions;

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\FleetLite\Data\OdometerReadingKind;
use App\Modules\FleetLite\Exceptions\TripLogException;
use App\Modules\FleetLite\Models\DepartureOdometerReading;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre (ou corrige) le relevé compteur de départ ou d'arrivée d'un
 * voyage. Un seul relevé par type et par départ ; la correction écrase la
 * valeur précédente et laisse une trace dans le journal d'activité.
 * Idempotent : rejouer la même saisie (même `client_uuid`, même valeur)
 * ne crée rien de plus.
 */
class RecordOdometerReadingAction
{
    /**
     * @return array{0: DepartureOdometerReading, 1: bool} Relevé et « créé ».
     *
     * @throws TripLogException
     */
    public function execute(
        User $user,
        Departure $departure,
        ?TerminalP501 $terminal,
        OdometerReadingKind $kind,
        int $valueKm,
        ?Carbon $recordedAt,
        ?string $clientUuid,
    ): array {
        return DB::transaction(function () use ($user, $departure, $terminal, $kind, $valueKm, $recordedAt, $clientUuid): array {
            $readings = DepartureOdometerReading::query()
                ->where('departure_id', $departure->getKey())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (DepartureOdometerReading $reading): string => $reading->kind->value);

            $start = $kind === OdometerReadingKind::Start ? $valueKm : $readings->get(OdometerReadingKind::Start->value)?->value_km;
            $end = $kind === OdometerReadingKind::End ? $valueKm : $readings->get(OdometerReadingKind::End->value)?->value_km;

            if ($start !== null && $end !== null && $end < $start) {
                throw TripLogException::odometerInconsistent($start, $end);
            }

            /** @var DepartureOdometerReading|null $existing */
            $existing = $readings->get($kind->value);
            $previousValue = $existing?->value_km;

            if ($existing !== null && $previousValue === $valueKm) {
                return [$existing, false];
            }

            $reading = $existing ?? new DepartureOdometerReading([
                'departure_id' => $departure->getKey(),
                'kind' => $kind,
            ]);

            $reading->fill([
                'bus_id' => $departure->bus_id,
                'value_km' => $valueKm,
                'recorded_at' => $recordedAt ?? now(),
                'recorded_by' => $user->getKey(),
                'terminal_p501_id' => $terminal?->getKey(),
                'client_uuid' => $clientUuid,
            ])->save();

            activity('trip_logs')
                ->performedOn($departure)
                ->causedBy($user)
                ->withProperties([
                    'kind' => $kind->value,
                    'value_km' => $valueKm,
                    'previous_value_km' => $previousValue,
                    'terminal' => $terminal?->serial_number,
                ])
                ->log($existing === null ? 'odometer_recorded' : 'odometer_corrected');

            return [$reading, $existing === null];
        }, attempts: 3);
    }
}
