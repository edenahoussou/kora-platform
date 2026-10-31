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
        return ['code' => 'ORG-'.fake()->unique()->bothify('????####'), 'name' => fake()->company(), 'status' => 'active'];
    }
}
