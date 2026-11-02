<?php

namespace Database\Factories\Modules\IdentityAccess\Models;

use App\Models\User;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KoraPointWebAccessInvitation>
 */
class KoraPointWebAccessInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => KoraPointWebAccessInvitation::STATUS_PENDING_DELIVERY,
            'delivery_channel' => null,
            'sent_at' => null,
            'expires_at' => null,
            'used_at' => null,
        ];
    }
}
