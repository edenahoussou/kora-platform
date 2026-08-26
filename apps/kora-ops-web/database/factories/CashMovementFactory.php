<?php

namespace Database\Factories;

use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    public function configure(): static
    {
        return $this->afterMaking(function (CashMovement $movement): void {
            $movement->cash_session_id = CashLedger::query()
                ->whereKey($movement->cash_ledger_id)
                ->value('cash_session_id');
        });
    }

    public function definition(): array
    {
        return [
            'cash_session_id' => null,
            'cash_ledger_id' => CashLedger::factory(),
            'type' => 'counter_sale_cash',
            'direction' => CashMovementDirection::In,
            'status' => CashMovementStatus::Posted,
            'amount' => fake()->numberBetween(1000, 100000),
            'currency' => 'XOF',
            'description' => 'Mouvement de test',
            'occurred_at' => now(),
        ];
    }

    public function pendingTransfer(): static
    {
        return $this->state(fn (): array => [
            'type' => 'reversement',
            'direction' => CashMovementDirection::Out,
            'status' => CashMovementStatus::Pending,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CashMovementStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
