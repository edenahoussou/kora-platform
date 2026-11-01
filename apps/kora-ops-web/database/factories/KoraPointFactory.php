<?php

namespace Database\Factories;

use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\KoraPoints\Models\KoraPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KoraPoint> */
class KoraPointFactory extends Factory
{
    protected $model = KoraPoint::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['agency_id' => Agency::factory()->state(['kind' => 'partner']), 'status' => 'active', 'responsible_name' => fake()->name(), 'phone' => '+22890000000'];
    }
}
