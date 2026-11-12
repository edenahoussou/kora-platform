<?php

namespace App\Modules\Expenses\Models;

use App\Models\User;
use App\Modules\FleetLite\Data\TripExpenseKind;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Détails terrain d'une dépense de voyage saisie depuis OPS Mobile
 * (carburant, péage, taxe frontière) — table satellite 1:1 de Expense.
 *
 * @property TripExpenseKind $kind
 * @property string|null $liters
 * @property int|null $unit_price
 * @property string|null $station_label
 * @property string|null $checkpoint_label
 * @property string|null $country_code
 * @property int|null $odometer_km
 * @property string $source
 * @property string $client_uuid
 * @property int|null $submitted_by
 */
class ExpenseTripDetails extends KoraModel
{
    protected $fillable = [
        'uuid',
        'expense_id',
        'kind',
        'liters',
        'unit_price',
        'station_label',
        'checkpoint_label',
        'country_code',
        'odometer_km',
        'source',
        'client_uuid',
        'submitted_by',
        'terminal_p501_id',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TripExpenseKind::class,
            'liters' => 'decimal:2',
            'unit_price' => 'integer',
            'odometer_km' => 'integer',
        ];
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
