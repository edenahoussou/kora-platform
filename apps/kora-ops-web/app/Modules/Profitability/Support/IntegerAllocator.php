<?php

namespace App\Modules\Profitability\Support;

/**
 * Répartit un montant entier selon des poids, sans perdre ni inventer un franc :
 * la somme des parts vaut toujours le montant distribué.
 */
final class IntegerAllocator
{
    /**
     * @param  array<int|string, float|int>  $weights
     * @return array<int|string, int>
     */
    public static function distribute(int $amount, array $weights): array
    {
        if ($weights === []) {
            return [];
        }

        $normalized = array_map(static fn (float|int $weight): float => max(0.0, (float) $weight), $weights);
        $total = array_sum($normalized);

        if ($total <= 0.0) {
            $normalized = array_fill_keys(array_keys($weights), 1.0);
            $total = (float) count($normalized);
        }

        $shares = [];
        $remainders = [];
        $allocated = 0;

        foreach ($normalized as $key => $weight) {
            $exact = $amount * $weight / $total;
            $floor = (int) floor($exact);
            $shares[$key] = $floor;
            $remainders[$key] = $exact - $floor;
            $allocated += $floor;
        }

        $leftover = $amount - $allocated;
        if ($leftover > 0) {
            arsort($remainders);
            $keys = array_keys($remainders);
            for ($index = 0; $index < $leftover; $index++) {
                $shares[$keys[$index % count($keys)]]++;
            }
        }

        return $shares;
    }
}
