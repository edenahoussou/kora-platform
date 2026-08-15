<?php

namespace Database\Factories;

use App\Modules\IdentityAccess\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPreference>
 */
class UserPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'push_notifications' => true,
            'whatsapp_notifications' => true,
            'email_notifications' => true,
            'locale' => 'fr',
            'theme' => 'light',
        ];
    }
}
