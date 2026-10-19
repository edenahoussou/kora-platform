<?php

namespace App\Modules\Expenses\Models;

use App\Modules\Expenses\Data\MaintenanceType;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Champs d'intervention d'une dépense de maintenance (garage, pièces,
 * main-d'œuvre, immobilisation) — table satellite 1:1 de Expense.
 *
 * @property MaintenanceType $maintenance_type
 * @property int|null $parts_amount
 * @property int|null $labor_amount
 * @property Carbon|null $immobilized_from
 * @property Carbon|null $immobilized_to
 */
class ExpenseMaintenanceDetails extends KoraModel
{
    protected $fillable = [
        'uuid',
        'expense_id',
        'maintenance_type',
        'garage_label',
        'parts_amount',
        'labor_amount',
        'immobilized_from',
        'immobilized_to',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_type' => MaintenanceType::class,
            'parts_amount' => 'integer',
            'labor_amount' => 'integer',
            'immobilized_from' => 'date',
            'immobilized_to' => 'date',
        ];
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
