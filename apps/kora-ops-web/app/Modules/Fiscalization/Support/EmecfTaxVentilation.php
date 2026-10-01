<?php

namespace App\Modules\Fiscalization\Support;

class EmecfTaxVentilation
{
    /**
     * @param  array<string, mixed>  $taxes
     * @param  array<mixed>  $items
     * @return list<array{label: string, total: float, imposable: float|null, ts: float, impot: float|null}>
     */
    public static function fromTaxes(array $taxes, ?string $aibCode = null, array $items = []): array
    {
        $tsTotal = self::number($taxes['ts'] ?? null);
        $tsShares = self::specificTaxShares($items);
        $tsSharesSum = array_sum($tsShares);
        $tsAssigned = false;
        $rows = [];

        foreach (self::definitions() as $definition) {
            $total = self::number($taxes[$definition['total']] ?? null);

            if ($total == 0.0) {
                continue;
            }

            $groupTs = 0.0;

            if ($tsTotal != 0.0) {
                if ($tsSharesSum > 0.0 && isset($tsShares[$definition['group']])) {
                    $groupTs = round($tsTotal * $tsShares[$definition['group']] / $tsSharesSum);
                } elseif ($tsSharesSum == 0.0 && ! $tsAssigned && $definition['imposable'] !== null) {
                    $groupTs = $tsTotal;
                    $tsAssigned = true;
                }
            }

            $imposable = $definition['imposable'] === null
                ? null
                : self::number($taxes[$definition['imposable']] ?? null) - $groupTs;

            $impot = $definition['impot'] === 'ts'
                ? ($groupTs != 0.0 ? $groupTs : null)
                : ($definition['impot'] === null ? null : self::number($taxes[$definition['impot']] ?? null));

            $rows[] = [
                'label' => $definition['label'],
                'total' => $total,
                'imposable' => $imposable,
                'ts' => $groupTs,
                'impot' => $impot,
            ];
        }

        $aibAmount = self::number($taxes['aibAmount'] ?? null);

        if ($aibAmount != 0.0) {
            $aibRate = match (strtoupper((string) $aibCode)) {
                'A' => '1%',
                'B' => '5%',
                default => null,
            };

            $rows[] = [
                'label' => 'AIB'.($aibRate !== null ? ' ('.$aibRate.')' : ''),
                'total' => $aibAmount,
                'imposable' => null,
                'ts' => 0.0,
                'impot' => $aibAmount,
            ];
        }

        return $rows;
    }

    /** Libellé court du groupe affiché à côté de la désignation, comme sur le site de vérification e-MECeF. */
    public static function groupTag(?string $group): string
    {
        return '('.match (strtoupper((string) $group)) {
            'A' => 'A-EX',
            'B', 'C', 'D', 'E', 'F' => strtoupper((string) $group),
            default => '—',
        }.')';
    }

    /**
     * Ventilation T.S. d'une ligne d'article, comme « T.S. 3 x 333 x 1,18 = 1 180 » sur le site e-MECeF.
     * taxSpecific est un montant total de ligne HT ; le coefficient est le taux TVA du groupe de l'article.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $taxes
     * @return array{unit: float, factor: float, amount: float}|null
     */
    public static function itemSpecificTax(array $item, array $taxes): ?array
    {
        $ts = self::number($item['taxSpecific'] ?? null);

        if ($ts == 0.0) {
            return null;
        }

        $quantity = abs((float) ($item['quantity'] ?? 0));
        $direction = (float) ($item['quantity'] ?? 0) < 0 ? -1.0 : 1.0;
        $base = abs($ts);
        $factor = 1 + self::vatRate($item['taxGroup'] ?? null, $taxes) / 100;

        return [
            'unit' => $quantity > 0 ? $base / $quantity : $base,
            'factor' => $factor,
            'amount' => $direction * $base * $factor,
        ];
    }

    /**
     * Taux de TVA du groupe, issu des champs ta..td de la réponse e-MECeF.
     *
     * @param  array<string, mixed>  $taxes
     */
    public static function vatRate(?string $group, array $taxes): float
    {
        $field = match (strtoupper((string) $group)) {
            'A' => 'ta',
            'B' => 'tb',
            'C' => 'tc',
            'D' => 'td',
            default => null,
        };

        return $field === null ? 0.0 : self::number($taxes[$field] ?? null);
    }

    /** @return list<array{label: string, group: string, total: string, imposable: string|null, impot: string|null}> */
    private static function definitions(): array
    {
        return [
            ['label' => 'A - EXONERE', 'group' => 'A', 'total' => 'taa', 'imposable' => null, 'impot' => null],
            ['label' => 'B - TVA 18%', 'group' => 'B', 'total' => 'tab', 'imposable' => 'hab', 'impot' => 'vab'],
            ['label' => 'C - EXPORTATION', 'group' => 'C', 'total' => 'tac', 'imposable' => null, 'impot' => null],
            ['label' => 'D - REGIME D’EXCEPTION', 'group' => 'D', 'total' => 'tad', 'imposable' => 'had', 'impot' => 'vad'],
            ['label' => 'E - REGIME TPS', 'group' => 'E', 'total' => 'tae', 'imposable' => null, 'impot' => null],
            ['label' => 'F - TAXE SPECIFIQUE', 'group' => 'F', 'total' => 'taf', 'imposable' => null, 'impot' => 'ts'],
        ];
    }

    /**
     * @param  array<mixed>  $items
     * @return array<string, float>
     */
    private static function specificTaxShares(array $items): array
    {
        $shares = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $ts = abs(self::number($item['taxSpecific'] ?? null));
            $group = strtoupper((string) ($item['taxGroup'] ?? ''));

            if ($ts > 0 && $group !== '') {
                $shares[$group] = ($shares[$group] ?? 0.0) + $ts;
            }
        }

        return $shares;
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
