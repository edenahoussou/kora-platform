<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    // Freeze time to the morning of the first departure's service day so
    // both departures remain sellable (cutoff is 30 minutes before
    // departure). Without this, the test depends on the real wall clock
    // and fails once the first departure's cutoff has passed.
    Carbon::setTestNow('2026-09-06 08:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('quotes a two-leg itinerary when the connection spans two service days', function (): void {
    $scenario = nightConnectionScenario();
    Sanctum::actingAs($scenario['user']);

    // Leg 1: Dassa -> Cotonou, departs 2026-09-06 17:00, arrives 2026-09-07 04:00.
    // Leg 2: Cotonou -> Porto-Novo, departs 2026-09-07 15:00, arrives 2026-09-07 16:27.
    // The two legs share the same physical connection point (Cotonou) but
    // belong to different service days. The 45-minute connection margin is
    // respected (11h between arrival and next departure).
    $quote = $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'departure_uuid' => $scenario['firstDeparture']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['connection']->uuid,
        ], [
            'departure_uuid' => $scenario['secondDeparture']->uuid,
            'origin_stop_uuid' => $scenario['secondConnection']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk();

    expect($quote->json('data.itinerary_quote_uuid'))->not->toBeNull();
});

/** @return array<string, mixed> */
function nightConnectionScenario(): array
{
    $user = User::factory()->create();

    $dassaPhysical = PhysicalStop::create(['name' => 'Dassa Physical', 'city' => 'Dassa', 'country' => 'BJ', 'status' => 'active']);
    $cotonouPhysical = PhysicalStop::create(['name' => 'Cotonou Physical', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $portoNovoPhysical = PhysicalStop::create(['name' => 'Porto-Novo Physical', 'city' => 'Porto-Novo', 'country' => 'BJ', 'status' => 'active']);

    $firstLine = Line::create(['code' => 'NC-L1', 'name' => 'Dassa-Cotonou', 'countries_crossed' => ['BJ'], 'status' => 'active']);
    $secondLine = Line::create(['code' => 'NC-L2', 'name' => 'Cotonou-Porto-Novo', 'countries_crossed' => ['BJ'], 'status' => 'active']);

    $origin = Stop::create(['line_id' => $firstLine->id, 'physical_stop_id' => $dassaPhysical->id, 'name' => 'Dassa Départ', 'city' => 'Dassa', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
    $connection = Stop::create(['line_id' => $firstLine->id, 'physical_stop_id' => $cotonouPhysical->id, 'name' => 'Cotonou Arrivée', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 2, 'status' => 'active']);
    $secondConnection = Stop::create(['line_id' => $secondLine->id, 'physical_stop_id' => $cotonouPhysical->id, 'name' => 'Cotonou Départ', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
    $destination = Stop::create(['line_id' => $secondLine->id, 'physical_stop_id' => $portoNovoPhysical->id, 'name' => 'Porto-Novo Arrivée', 'city' => 'Porto-Novo', 'country' => 'BJ', 'stop_order' => 2, 'status' => 'active']);

    $firstBus = Bus::create(['registration_number' => 'NC-BUS-1', 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);
    $secondBus = Bus::create(['registration_number' => 'NC-BUS-2', 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);
    Seat::create(['bus_id' => $firstBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    Seat::create(['bus_id' => $secondBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    // First departure: service day 2026-09-06, leaves 17:00, arrives Cotonou 04:00 next day.
    $firstDeparture = Departure::create([
        'line_id' => $firstLine->id,
        'bus_id' => $firstBus->id,
        'departure_date' => '2026-09-06',
        'departure_time' => '17:00',
        'scheduled_at' => CarbonImmutable::parse('2026-09-06 17:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $origin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $connection->uuid, 'departure_minutes' => null, 'arrival_minutes' => 660, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse('2026-09-07 04:00', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    // Second departure: service day 2026-09-07, leaves 15:00, arrives Porto-Novo 16:27.
    $secondDeparture = Departure::create([
        'line_id' => $secondLine->id,
        'bus_id' => $secondBus->id,
        'departure_date' => '2026-09-07',
        'departure_time' => '15:00',
        'scheduled_at' => CarbonImmutable::parse('2026-09-07 15:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $secondConnection->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $destination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 87, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse('2026-09-07 16:27', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    Fare::create(['line_id' => $firstLine->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $connection->id, 'base_amount' => 4400, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);
    Fare::create(['line_id' => $secondLine->id, 'origin_stop_id' => $secondConnection->id, 'destination_stop_id' => $destination->id, 'base_amount' => 1100, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);

    return compact('user', 'dassaPhysical', 'cotonouPhysical', 'portoNovoPhysical', 'origin', 'connection', 'secondConnection', 'destination', 'firstDeparture', 'secondDeparture');
}
