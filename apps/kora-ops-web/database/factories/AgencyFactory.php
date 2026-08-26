<?php

namespace Database\Factories;

use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('AG??'));

        return [
            'code' => $code,
            'name' => 'Agence '.$code,
            'city' => fake()->city(),
            'country' => 'BJ',
            'status' => AgencyStatus::Active,
        ];
    }
}
