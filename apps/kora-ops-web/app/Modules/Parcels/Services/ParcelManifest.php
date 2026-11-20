<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Modules\Parcels\Models\ParcelAssignment;
use Carbon\CarbonInterface;

/**
 * Builds the hold manifest for a real Bus departure.
 *
 * The manifest is intentionally public-reference only: it never exposes
 * parcel, assignment, agency or user identifiers. It is sorted by the
 * departure stop sequence so ground staff can load the hold stop by stop.
 */
class ParcelManifest
{
    public function authorize(User $user, Departure $departure, CrewDepartureScope $crew): void
    {
        abort_unless(
            ($user->can('parcels.network') && $user->can('parcels.view'))
            || $crew->query($user, 'parcels.scan')->whereKey($departure->id)->exists(),
            403,
        );
    }

    /** @return array<string, mixed> */
    public function build(Departure $departure): array
    {
        $departure->loadMissing(['line.stops', 'bus']);
        $stops = $departure->tripStops()->values();
        $order = $stops->pluck('id')->flip();

        $rows = ParcelAssignment::query()
            ->where('departure_id', $departure->id)
            ->where('bus_id', $departure->bus_id)
            ->whereNull('released_at')
            ->with(['parcel.corridor.originHub.stop', 'parcel.corridor.destinationHub.stop'])
            ->get()
            ->map(function (ParcelAssignment $assignment) use ($order): array {
                $parcel = $assignment->parcel;
                $corridor = $parcel?->corridor;
                $origin = $assignment->return_leg ? $corridor?->destinationHub?->stop : $corridor?->originHub?->stop;
                $destination = $assignment->return_leg ? $corridor?->originHub?->stop : $corridor?->destinationHub?->stop;
                $trackingUrl = $parcel?->reference
                    ? route('api.v1.parcels.tracking', ['reference' => $parcel->reference])
                    : null;

                return [
                    'reference' => $parcel?->reference,
                    'weight_grams' => $parcel?->weight_grams,
                    'status' => $parcel?->status,
                    'origin_stop_uuid' => $origin?->uuid,
                    'origin_stop' => $origin?->name,
                    'destination_stop_uuid' => $destination?->uuid,
                    'destination_stop' => $destination?->name,
                    'load_stop_uuid' => $origin?->uuid,
                    'load_stop' => $origin?->name,
                    'loaded_at' => $assignment->loaded_at?->toIso8601String(),
                    'qr_payload' => $trackingUrl,
                    'origin_order' => $origin === null ? PHP_INT_MAX : (int) ($order[$origin->id] ?? PHP_INT_MAX),
                    'destination_order' => $destination === null ? PHP_INT_MAX : (int) ($order[$destination->id] ?? PHP_INT_MAX),
                ];
            })
            ->sortBy(fn (array $row): array => [$row['origin_order'], $row['destination_order'], (string) $row['reference']])
            ->values()
            ->map(function (array $row, int $index): array {
                unset($row['origin_order'], $row['destination_order']);

                return ['sequence' => $index + 1] + $row;
            });

        $first = $stops->first();
        $last = $stops->last();
        $payload = [
            'departure' => [
                'uuid' => $departure->uuid,
                'line_code' => $departure->line?->code,
                'line_name' => $departure->line?->name,
                'bus_registration' => $departure->bus?->registration_number,
                'departure_date' => $departure->departure_date instanceof CarbonInterface
                    ? $departure->departure_date->toDateString()
                    : (string) $departure->departure_date,
                'departure_time' => substr((string) $departure->departure_time, 0, 5),
                'status' => $departure->status->value,
                'origin_stop' => $first?->name,
                'destination_stop' => $last?->name,
            ],
            'stops' => $stops->values()->map(fn (Stop $stop, int $index): array => [
                'sequence' => $index + 1,
                'uuid' => $stop->uuid,
                'name' => $stop->name,
                'city' => $stop->city,
            ])->all(),
            'summary' => [
                'parcels' => $rows->count(),
                'weight_grams' => (int) $rows->sum('weight_grams'),
                'loaded' => $rows->whereNotNull('loaded_at')->count(),
            ],
            'parcels' => $rows->all(),
        ];
        $version = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return $payload + [
            'version' => $version,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
