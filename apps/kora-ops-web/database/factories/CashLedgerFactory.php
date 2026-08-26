<?php

namespace Database\Factories;

use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashLedger>
 */
class CashLedgerFactory extends Factory
{
    protected $model = CashLedger::class;

    public function definition(): array
    {
        $openingBalance = fake()->numberBetween(0, 500000);

        return [
            'cash_session_id' => CashSession::factory(),
            'ledger_key' => 'cash',
            'kind' => CashLedgerKind::Cash,
            'opening_balance' => $openingBalance,
            'expected_balance' => $openingBalance,
            'status' => CashLedgerStatus::Open,
            'opened_at' => now(),
        ];
    }

    public function mobileMoney(int $accountId, string $accountUuid): static
    {
        return $this->state(fn (): array => [
            'ledger_key' => $accountUuid,
            'kind' => CashLedgerKind::MobileMoney,
            'agency_payment_account_id' => $accountId,
        ]);
    }

    public function mtn(int $accountId, string $accountUuid): static
    {
        return $this->mobileMoney($accountId, $accountUuid);
    }

    public function moovFlooz(int $accountId, string $accountUuid): static
    {
        return $this->mobileMoney($accountId, $accountUuid);
    }

    public function celtiis(int $accountId, string $accountUuid): static
    {
        return $this->mobileMoney($accountId, $accountUuid);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'actual_balance' => fn (array $attributes): int => (int) $attributes['expected_balance'],
            'variance' => 0,
            'status' => CashLedgerStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
