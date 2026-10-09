<?php

namespace App\Modules\Departures\Services;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Departures\Models\Departure;

/**
 * Charge par tronçon : un siège peut être revendu sur chaque portion
 * disjointe de la ligne, le remplissage d'un départ se mesure donc sur
 * chaque tronçon entre deux arrêts consécutifs — pas en divisant un
 * nombre de billets par la capacité.
 */
class DepartureOccupancyService
{
    /**
     * @return array{passengers: int, peakFillRate: int, averageFillRate: int, peakLegLabel: string|null, peakLoad: int, passengerLegs: int, seatLegs: int}
     */
    public function occupancy(Departure $departure, ?int $seatCount = null): array
    {
        $seatCount ??= $departure->bus->seat_count ?? 0;

        $tripStops = $departure->tripStops()->values();
        $stopTimes = $departure->stop_times ?? [];

        // Les *_stop_order des segments sont des positions dans
        // stop_times quand le départ en a une, sinon des stop_order
        // canoniques — les deux bases sont mappées vers la position
        // dans l'ordre de circulation réel.
        $positionOf = [];
        if ($stopTimes !== []) {
            foreach (array_keys($stopTimes) as $index) {
                $position = $tripStops->search(
                    fn ($stop): bool => $stop->uuid === ($stopTimes[$index]['stop_uuid'] ?? null)
                );
                if ($position !== false) {
                    $positionOf[$index] = $position;
                }
            }
        } else {
            $positionOf = array_flip($tripStops->pluck('stop_order')->map(fn ($order) => (int) $order)->all());
        }

        $legCount = max(0, $tripStops->count() - 1);

        $segments = $departure->reservationSegments()
            ->whereIn('status', ReservationSegmentStatus::occupyingValues())
            ->get(['reservation_id', 'origin_stop_order', 'destination_stop_order']);

        $passengers = $segments->pluck('reservation_id')->unique()->count();

        $legLoads = array_fill(0, $legCount, 0);
        foreach ($segments as $segment) {
            $from = $positionOf[(int) $segment->origin_stop_order] ?? null;
            $to = $positionOf[(int) $segment->destination_stop_order] ?? null;
            if ($from === null || $to === null || $to <= $from) {
                continue;
            }
            for ($leg = $from; $leg < $to; $leg++) {
                $legLoads[$leg]++;
            }
        }

        $peakLoad = $legLoads === [] ? 0 : max($legLoads);
        $peakIndex = $legLoads === [] ? false : array_search($peakLoad, $legLoads, true);
        $peakLegLabel = null;

        if (is_int($peakIndex) && $peakLoad > 0 && isset($tripStops[$peakIndex], $tripStops[$peakIndex + 1])) {
            $peakLegLabel = $tripStops[$peakIndex]->physicalStop->name
                .' → '
                .$tripStops[$peakIndex + 1]->physicalStop->name;
        }

        $passengerLegs = array_sum($legLoads);
        $seatLegs = $legCount * $seatCount;

        return [
            'passengers' => $passengers,
            'peakFillRate' => $seatCount > 0 ? (int) round($peakLoad / $seatCount * 100) : 0,
            'averageFillRate' => $seatLegs > 0 ? (int) round($passengerLegs / $seatLegs * 100) : 0,
            'peakLegLabel' => $peakLegLabel,
            'peakLoad' => $peakLoad,
            'passengerLegs' => $passengerLegs,
            'seatLegs' => $seatLegs,
        ];
    }
}
