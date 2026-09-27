<?php

namespace App\Modules\Departures\Actions;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Models\PlannedDeparture;
use App\Modules\Departures\Services\PublicationService;
use App\Modules\DynamicPricing\Models\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDepartureAction
{
    public function __construct(private PublicationService $publication) {}

    /**
     * @param  array{line_uuid?: string, bus_uuid?: string, departure_date?: string, departure_time?: string, driver_uuid?: string|null, conductor_uuid?: string|null}  $attributes
     */
    public function execute(Departure $departure, array $attributes): Departure
    {
        $currentStatus = DepartureStatus::from((string) $departure->getRawOriginal('status'));
        if ($currentStatus !== DepartureStatus::Scheduled) {
            throw ValidationException::withMessages([
                'status' => 'Seuls les départs au statut PROGRAMMÉ peuvent être modifiés.',
            ]);
        }

        return DB::transaction(function () use ($departure, $attributes): Departure {
            $departure = Departure::query()->whereKey($departure->id)->lockForUpdate()->firstOrFail();

            // ✅ REFACTORISATION : Séparation des conditions pour aider PHPStan à résoudre les types
            $hasActiveSegments = $departure->reservationSegments()
                ->whereNotIn('status', ['expired', 'cancelled'])
                ->exists();

            $hasSegmentsWithTickets = $departure->reservationSegments()
                ->whereHas('reservation', function (Builder $query): void {
                    $query->whereHas('tickets');
                })
                ->exists();

            $hasSegmentsWithPayments = $departure->reservationSegments()
                ->whereHas('reservation', function (Builder $query): void {
                    $query->whereHas('payments', function (Builder $q): void {
                        $q->whereIn('status', ['pending', 'confirmed', 'refunded']);
                    });
                })
                ->exists();

            if ($departure->getRawOriginal('status') !== 'scheduled' || $hasActiveSegments || $hasSegmentsWithTickets || $hasSegmentsWithPayments) {
                throw ValidationException::withMessages(['departure' => 'Ce départ est protégé par une réservation ou son état opérationnel.']);
            }

            if (PlannedDeparture::query()->where('departure_id', $departure->id)->exists()) {
                throw ValidationException::withMessages(['departure' => 'Modifiez ce départ depuis Programmation pour conserver sa provenance.']);
            }

            PriceQuote::query()->where('departure_id', $departure->id)->update(['expires_at' => now()]);

            $lineId = $departure->line_id;
            if (isset($attributes['line_uuid'])) {
                $lineId = Line::query()->where('uuid', $attributes['line_uuid'])->firstOrFail(['id'])->id;
            }
            $line = Line::query()->findOrFail($lineId);

            $busId = $departure->bus_id;
            if (isset($attributes['bus_uuid'])) {
                $busId = Bus::query()->where('uuid', $attributes['bus_uuid'])->firstOrFail(['id'])->id;
            }

            $driverId = $departure->driver_id;
            if (array_key_exists('driver_uuid', $attributes)) {
                $driverId = $attributes['driver_uuid'] !== null
                    ? User::query()->where('uuid', $attributes['driver_uuid'])->firstOrFail(['id'])->id
                    : null;
            }

            $conductorId = $departure->conductor_id;
            if (array_key_exists('conductor_uuid', $attributes)) {
                $conductorId = $attributes['conductor_uuid'] !== null
                    ? User::query()->where('uuid', $attributes['conductor_uuid'])->firstOrFail(['id'])->id
                    : null;
            }

            $date = $attributes['departure_date'] ?? $departure->departure_date->format('Y-m-d');
            $time = $attributes['departure_time'] ?? $departure->departure_time;
            $timezone = $departure->departure_timezone ?? config('app.timezone');
            $departureDateTime = CarbonImmutable::parse($date.' '.$time, $timezone);
            $estimatedArrivalAt = $line->estimated_duration_minutes
                ? $departureDateTime->addMinutes((int) $line->estimated_duration_minutes)->utc()
                : null;

            $departure->fill([
                'line_id' => $lineId,
                'bus_id' => $busId,
                'departure_date' => $date,
                'departure_time' => $time,
                'scheduled_at' => $departureDateTime,
                'departure_timezone' => $timezone,
                'estimated_arrival_at' => $estimatedArrivalAt,
                'driver_id' => $driverId,
                'conductor_id' => $conductorId,
            ]);

            $this->publication->guardOperationalResources($departure);
            $departure->save();

            return $departure->refresh();
        });
    }
}
