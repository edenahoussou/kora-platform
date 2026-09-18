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
        // Mirrors the real AG-000001 format produced by
        // GenerateAgencyCodeAction, without touching the sequence table, so
        // factory-created agencies don't collide with server-generated ones.
        $code = sprintf('AG-%06d', fake()->unique()->numberBetween(900000, 999999));

        return [
            'code' => $code,
            'name' => 'Agence '.$code,
            'city' => fake()->city(),
            'country' => 'BJ',
            'call_phone' => null,
            'whatsapp_phone' => null,
            'status' => AgencyStatus::Active,
        ];
    }
}
