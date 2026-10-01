<?php

namespace App\Modules\Fiscalization\Support;

class EmecfTaxVentilation
{
    /**
     * @param  array<string, mixed>  $taxes
     * @return list<array{label: string, total: float, imposable: float|null, impot: float|null}>
     */
    public static function fromTaxes(array $taxes, ?string $aibCode = null): array
    {
        $rows = [];

        foreach (self::definitions() as $definition) {
            $total = self::number($taxes[$definition['total']] ?? null);

            if ($total == 0.0) {
                continue;
            }

            $rows[] = [
                'label' => $definition['label'],
                'total' => $total,
                'imposable' => $definition['imposable'] === null ? null : self::number($taxes[$definition['imposable']] ?? null),
                'impot' => $definition['impot'] === null ? null : self::number($taxes[$definition['impot']] ?? null),
            ];
        }

        $aibAmount = self::number($taxes['aibAmount'] ?? null);

        if ($aibAmount != 0.0) {
            $rows[] = [
                'label' => 'AIB'.($aibCode !== null ? ' ('.$aibCode.')' : ''),
                'total' => $aibAmount,
                'imposable' => null,
                'impot' => $aibAmount,
            ];
        }

        return $rows;
    }

    /** Libellé court du groupe affiché à côté de la désignation, comme sur le site de vérification e-MECeF. */
    public static function groupTag(?string $group): string
    {
        return match (strtoupper((string) $group)) {
            'A' => 'A-EX',
            'B', 'C', 'D', 'E', 'F' => strtoupper((string) $group),
            default => '—',
        };
    }

    /** Tag complet entre parenthèses, avec la taxe spécifique éventuelle : « (A-EX) », « (F · TS 500) ». */
    public static function itemTag(?string $group, mixed $taxSpecific = null): string
    {
        $tag = self::groupTag($group);
        if (is_numeric($taxSpecific) && (float) $taxSpecific > 0) {
            $tag .= ' · TS '.number_format((float) $taxSpecific, 0, ',', ' ');
        }

        return '('.$tag.')';
    }

    /** @return list<array{label: string, total: string, imposable: string|null, impot: string|null}> */
    private static function definitions(): array
    {
        return [
            ['label' => 'A - EXONERE', 'total' => 'taa', 'imposable' => null, 'impot' => null],
            ['label' => 'B - TVA 18%', 'total' => 'tab', 'imposable' => 'hab', 'impot' => 'vab'],
            ['label' => 'C - EXPORTATION', 'total' => 'tac', 'imposable' => null, 'impot' => null],
            ['label' => 'D - REGIME D’EXCEPTION', 'total' => 'tad', 'imposable' => 'had', 'impot' => 'vad'],
            ['label' => 'E - REGIME TPS', 'total' => 'tae', 'imposable' => null, 'impot' => null],
            ['label' => 'F - TAXE SPECIFIQUE', 'total' => 'taf', 'imposable' => null, 'impot' => 'ts'],
        ];
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
