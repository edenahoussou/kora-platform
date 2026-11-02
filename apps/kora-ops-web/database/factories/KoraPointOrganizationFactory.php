<?php

namespace Database\Factories;

use App\Modules\KoraPoints\Models\KoraPointOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KoraPointOrganization> */
class KoraPointOrganizationFactory extends Factory
{
    protected $model = KoraPointOrganization::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'ORG-'.fake()->unique()->bothify('????####'),
            'name' => fake()->company(),
            'status' => 'active',
            'country' => 'BJ',
            'commission_basis_points' => 300,
            'ceiling_amount' => 1_500_000,
            'settlement_calendar' => [
                'frequency' => 'weekly',
                'weekday' => 1,
                'day' => null,
                'time' => '18:00',
                'timezone' => 'Africa/Porto-Novo',
            ],
        ];
    }
}
