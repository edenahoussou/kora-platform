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

        $stops = $departure->line?->stops;
        $orders = $stops?->pluck('stop_order')->map(fn ($order) => (int) $order)->values()->all() ?? [];
        $legCount = max(0, count($orders) - 1);
        $positionOf = array_flip($orders);

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

        if ($stops !== null && $peakIndex !== false && $peakLoad > 0) {
            $peakLegLabel = $stops[$peakIndex]->physicalStop->name
                .' → '
                .$stops[$peakIndex + 1]->physicalStop->name;
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
