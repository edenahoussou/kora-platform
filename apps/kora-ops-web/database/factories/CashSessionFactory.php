<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashSession;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openingFloat = fake()->numberBetween(0, 500000);

        return [
            'agent_id' => User::factory(),
            'agency_id' => Agency::factory(),
            'cash_register_id' => null,
            'business_date' => now()->toDateString(),
            'opening_float' => $openingFloat,
            'total_cash' => 0,
            'total_mobile_money' => 0,
            'expected_balance' => $openingFloat,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CashSessionStatus::Closed,
            'closed_at' => now(),
            'actual_balance' => $attributes['expected_balance'],
            'variance' => 0,
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (): array => [
            'status' => CashSessionStatus::PendingApproval,
            'submitted_at' => now(),
        ]);
    }
}
