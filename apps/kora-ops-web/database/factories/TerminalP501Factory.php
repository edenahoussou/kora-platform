<?php

namespace Database\Factories;

use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TerminalP501>
 */
class TerminalP501Factory extends Factory
{
    protected $model = TerminalP501::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'serial_number' => 'P501-'.strtoupper(fake()->unique()->bothify('??######')),
            'assigned_user_id' => null,
            'agency_id' => null,
            'status' => TerminalP501Status::Active,
            'last_sync_at' => null,
            'last_handshake_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TerminalP501Status::Active,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TerminalP501Status::Inactive,
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TerminalP501Status::Maintenance,
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TerminalP501Status::Lost,
            'assigned_user_id' => null,
        ]);
    }
}
