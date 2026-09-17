<?php

namespace Database\Factories;

use App\Modules\Cash\Data\CashRegisterKind;
use App\Modules\Cash\Data\CashRegisterStatus;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CashRegister> */
class CashRegisterFactory extends Factory
{
    protected $model = CashRegister::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $code = 'G-'.fake()->unique()->numberBetween(1, 999);

        return [
            'agency_id' => Agency::factory(),
            'code' => $code,
            'label' => 'Guichet '.$code,
            'kind' => CashRegisterKind::Counter,
            'status' => CashRegisterStatus::Active,
        ];
    }

    public function vault(): static
    {
        return $this->state([
            'code' => 'COFFRE',
            'label' => 'Coffre agence',
            'kind' => CashRegisterKind::Vault,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => CashRegisterStatus::Inactive]);
    }
}
