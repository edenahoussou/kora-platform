<?php

namespace App\Modules\Profitability\Services;

/**
 * Seuil de rentabilité en sièges à partir des coûts fixes, des coûts
 * variables, de la recette nette et des sièges vendus.
 */
class BreakEvenCalculator
{
    public const STATUS_ATTEINT = 'atteint';

    public const STATUS_SOUS_SEUIL = 'sous_seuil';

    public const STATUS_NON_CALCULABLE = 'non_calculable';

    /**
     * @param  array{revenueNet: int, costFixed: int, costVariable: int, seatsSold: int, capacity: int|null}  $metrics
     * @return array{contributionPerSeat: float|null, breakEvenSeats: int|null, seatsSold: int, capacity: int|null, fillRate: float|null, status: string}
     */
    public function calculate(array $metrics): array
    {
        $seatsSold = max(0, $metrics['seatsSold']);
        $capacity = $metrics['capacity'];
        $contribution = (float) $metrics['revenueNet'] - (float) $metrics['costVariable'];
        $contributionPerSeat = $seatsSold > 0 ? $contribution / $seatsSold : null;

        if ($contributionPerSeat === null || $contributionPerSeat <= 0) {
            return [
                'contributionPerSeat' => $contributionPerSeat,
                'breakEvenSeats' => null,
                'seatsSold' => $seatsSold,
                'capacity' => $capacity,
                'fillRate' => $capacity !== null && $capacity > 0 ? round($seatsSold / $capacity * 100, 1) : null,
                'status' => self::STATUS_NON_CALCULABLE,
            ];
        }

        $breakEvenSeats = (int) ceil(max(0, $metrics['costFixed']) / $contributionPerSeat);

        return [
            'contributionPerSeat' => round($contributionPerSeat, 2),
            'breakEvenSeats' => $breakEvenSeats,
            'seatsSold' => $seatsSold,
            'capacity' => $capacity,
            'fillRate' => $capacity !== null && $capacity > 0 ? round($seatsSold / $capacity * 100, 1) : null,
            'status' => $seatsSold >= $breakEvenSeats ? self::STATUS_ATTEINT : self::STATUS_SOUS_SEUIL,
        ];
    }
}
