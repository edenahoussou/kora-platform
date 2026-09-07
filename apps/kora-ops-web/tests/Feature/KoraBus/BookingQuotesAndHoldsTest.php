<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PricingRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('expires past-due holds with bus:expire-holds command', function (): void {
    $scenario = bookingTestScenario();

    $reservation = Reservation::create([
        'created_by_user_id' => $scenario['user']->id,
        'status' => ReservationStatus::Held,
        'total_amount' => 10000,
        'currency' => 'XOF',
        'expires_at' => now()->subMinutes(5),
        'draft_data' => ['passengers' => ['seat-draft' => ['first_name' => 'Brouillon']]],
    ]);

    $segment = ReservationSegment::create([
        'reservation_id' => $reservation->id,
        'departure_id' => $scenario['departure']->id,
        'seat_id' => $scenario['seat2B']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'origin_stop_order' => $scenario['origin']->stop_order,
        'destination_stop_order' => $scenario['destination']->stop_order,
        'status' => ReservationSegmentStatus::Held,
    ]);

    $this->artisan('bus:expire-holds')
        ->expectsOutputToContain('Expired 1 seat hold(s).')
        ->assertSuccessful();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Expired);
    expect($reservation->fresh()->draft_data)->toBeNull();
    expect($segment->fresh()->status)->toBe(ReservationSegmentStatus::Expired);
    expect($segment->fresh()->released_at)->not->toBeNull();
});

/**
 * @return array<string, mixed>
 */
function bookingTestScenario(): array
{
    $user = User::factory()->create();

    $line = Line::query()->firstOrCreate(
        ['code' => 'BK-L1'],
        [
            'name' => 'Cotonou - Abidjan Booking',
            'countries_crossed' => ['BJ', 'TG', 'CI'],
            'distance_km' => 1020,
            'estimated_duration_minutes' => 1260,
            'status' => 'active',
        ],
    );

    $originPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'Cotonou Central BK'],
        ['city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active'],
    );
    $origin = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 1],
        ['physical_stop_id' => $originPhysical->id, 'name' => 'Cotonou BK', 'city' => 'Cotonou', 'country' => 'BJ', 'km_from_origin' => 0, 'status' => 'active'],
    );
    $middlePhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'Lome Central BK'],
        ['city' => 'Lome', 'country' => 'TG', 'status' => 'active'],
    );
    $middle = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 2],
        ['physical_stop_id' => $middlePhysical->id, 'name' => 'Lome BK', 'city' => 'Lome', 'country' => 'TG', 'km_from_origin' => 170, 'status' => 'active'],
    );
    $destinationPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'Abidjan Central BK'],
        ['city' => 'Abidjan', 'country' => 'CI', 'status' => 'active'],
    );
    $destination = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 3],
        ['physical_stop_id' => $destinationPhysical->id, 'name' => 'Abidjan BK', 'city' => 'Abidjan', 'country' => 'CI', 'km_from_origin' => 1020, 'status' => 'active'],
    );

    $bus = Bus::query()->firstOrCreate(
        ['registration_number' => 'BK-BUS-001'],
        ['brand' => 'KORA', 'model' => 'Coach BK', 'seat_count' => 3, 'status' => 'active'],
    );

    $seat1A = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '1A'],
        ['type' => 'crew', 'sellable' => false, 'status' => 'blocked'],
    );
    $seat2A = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '2A'],
        ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
    );
    $seat2B = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '2B'],
        ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
    );

    $departure = Departure::query()->firstOrCreate(
        ['line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => '2026-08-20', 'departure_time' => '08:00'],
        ['estimated_arrival_at' => CarbonImmutable::parse('2026-08-20 20:00'), 'status' => 'scheduled'],
    );

    $fare = Fare::query()->firstOrCreate(
        ['line_id' => $line->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id],
        ['base_amount' => 10000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active'],
    );

    // Add dynamic pricing rule (+10%)
    PricingRule::query()->firstOrCreate(
        ['line_id' => $line->id, 'type' => PricingRuleType::Increase->value],
        [
            'percentage' => 10.0,
            'priority' => 10,
            'status' => 'active',
            'conditions' => [],
        ],
    );

    // Existing reservation on segment 1->2 for seat 2A
    $existingRes = Reservation::query()->create([
        'status' => 'confirmed',
        'total_amount' => 5000,
        'currency' => 'XOF',
        'channel' => 'online',
    ]);

    ReservationSegment::query()->firstOrCreate(
        ['reservation_id' => $existingRes->id, 'seat_id' => $seat2A->id],
        [
            'departure_id' => $departure->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $middle->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $middle->stop_order,
            'status' => 'confirmed',
        ],
    );

    return compact('user', 'line', 'origin', 'middle', 'destination', 'bus', 'seat1A', 'seat2A', 'seat2B', 'departure', 'fare');
}
