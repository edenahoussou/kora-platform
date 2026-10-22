<?php

namespace App\Modules\Profitability\Data;

use App\Modules\Expenses\Data\MaintenanceType;
use App\Modules\Expenses\Models\Expense;

/**
 * Mapping par défaut d'une catégorie de dépense vers son comportement
 * (fixe/variable), sa règle de répartition et son libellé de poste P&L.
 */
final class CostCategory
{
    /** @var array<string, array{label: string, behavior: CostBehavior, rule: CostAllocationRule}> */
    private const DEFAULTS = [
        'fuel' => ['label' => 'Carburant', 'behavior' => CostBehavior::Variable, 'rule' => CostAllocationRule::BusKm],
        'maintenance' => ['label' => 'Maintenance', 'behavior' => CostBehavior::Variable, 'rule' => CostAllocationRule::BusKm],
        'tolls' => ['label' => 'Péages', 'behavior' => CostBehavior::Variable, 'rule' => CostAllocationRule::BusKm],
        'tires' => ['label' => 'Pneus', 'behavior' => CostBehavior::Variable, 'rule' => CostAllocationRule::BusKm],
        'insurance' => ['label' => 'Assurance', 'behavior' => CostBehavior::Fixed, 'rule' => CostAllocationRule::BusKm],
        'leasing' => ['label' => 'Leasing', 'behavior' => CostBehavior::Fixed, 'rule' => CostAllocationRule::BusKm],
        'agency_supplies' => ['label' => 'Fournitures', 'behavior' => CostBehavior::Fixed, 'rule' => CostAllocationRule::EqualSplit],
        'services' => ['label' => 'Services', 'behavior' => CostBehavior::Fixed, 'rule' => CostAllocationRule::EqualSplit],
        'other' => ['label' => 'Autres', 'behavior' => CostBehavior::Fixed, 'rule' => CostAllocationRule::EqualSplit],
    ];

    /** @var list<string> */
    public const POST_ORDER = ['fuel', 'maintenance', 'tolls', 'tires', 'insurance', 'leasing', 'agency_supplies', 'services', 'other'];

    public static function behavior(Expense $expense): CostBehavior
    {
        $code = self::code($expense);
        if ($code === 'maintenance') {
            return $expense->maintenanceDetails?->maintenance_type === MaintenanceType::Preventive
                ? CostBehavior::Fixed
                : CostBehavior::Variable;
        }

        return self::DEFAULTS[$code]['behavior'] ?? CostBehavior::Fixed;
    }

    public static function rule(Expense $expense): CostAllocationRule
    {
        return self::DEFAULTS[self::code($expense)]['rule'] ?? CostAllocationRule::EqualSplit;
    }

    public static function label(Expense $expense): string
    {
        return self::DEFAULTS[self::code($expense)]['label'] ?? 'Autres';
    }

    public static function labelForCode(string $code): string
    {
        return self::DEFAULTS[$code]['label'] ?? 'Autres';
    }

    public static function code(Expense $expense): string
    {
        $code = (string) ($expense->category->code ?? 'other');

        return isset(self::DEFAULTS[$code]) ? $code : 'other';
    }
}
