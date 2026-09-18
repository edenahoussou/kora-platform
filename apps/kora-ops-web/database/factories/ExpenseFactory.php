<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Expenses\Data\ExpensePaymentChannel;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'expense_category_id' => ExpenseCategory::factory(),
            'status' => ExpenseStatus::Pending,
            'payment_channel' => ExpensePaymentChannel::Cash,
            'amount' => fake()->numberBetween(1000, 100000),
            'currency' => 'XOF',
            'payee_label' => fake()->company(),
            'payee_reference' => fake()->optional()->numerify('REF-#####'),
            'description' => fake()->sentence(),
            'occurred_at' => now(),
            'created_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ExpenseStatus::Approved,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
