<?php

namespace Database\Factories;

use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpenseCategory> */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        $label = fake()->unique()->sentence(2);

        return [
            'code' => fake()->unique()->slug(2),
            'label' => ucfirst($label),
            'is_active' => true,
        ];
    }
}
