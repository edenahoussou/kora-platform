<?php

namespace Database\Factories;

use App\Modules\AssistedBooking\Data\AssistedBookingChannel;
use App\Modules\AssistedBooking\Data\AssistedBookingStatus;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistedBooking>
 */
class AssistedBookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => fn (): int => Reservation::query()->create([
                'status' => ReservationStatus::AssistancePending,
                'total_amount' => 10000,
                'currency' => 'XOF',
                'channel' => ReservationChannel::Mobile,
                'expires_at' => now()->addDay(),
            ])->getKey(),
            'preferred_agency_id' => Agency::factory()->state(['call_phone' => '+2290197000000']),
            'origin_channel' => ReservationChannel::Mobile,
            'handoff_channel' => AssistedBookingChannel::WhatsApp,
            'status' => AssistedBookingStatus::Pending,
            'contact_name' => fake()->firstName(),
            'contact_phone' => '+22901'.fake()->numerify('########'),
            'snapshot' => ['legs' => [], 'requested_seats' => [], 'passenger_count' => 1],
            'original_total_amount' => 10000,
            'currency' => 'XOF',
            'idempotency_key' => fake()->uuid(),
            'expires_at' => now()->addDay(),
        ];
    }
}
