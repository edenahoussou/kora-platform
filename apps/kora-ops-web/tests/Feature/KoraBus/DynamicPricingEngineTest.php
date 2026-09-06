<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\PricingSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-01 08:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Scénario autonome avec un bus de 4 sièges vendables, dont $occupiedSeats sont
 * déjà occupés sur le segment demandé — pour contrôler précisément le taux de
 * remplissage utilisé par l'évaluation des conditions. Le départ est positionné
 * $hoursFromNow heures après l'horloge de test, pour contrôler le délai.
 *
 * @return array{user: User, departure: Departure, origin: Stop, destination: Stop}
 */
function pricingEngineScenario(int $occupiedSeats, float $hoursFromNow): array
{
    $line = Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'PE-'.Str::random(6),
        'name' => 'Pricing Engine Line',
        'type' => 'nationale',
        'status' => 'active',
    ]);

    $originPhysical = PhysicalStop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'name' => 'Origine Physical',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $origin = Stop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'physical_stop_id' => $originPhysical->id,
        'name' => 'Origine',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);

    $destinationPhysical = PhysicalStop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'name' => 'Destination Physical',
        'city' => 'Parakou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $destination = Stop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'physical_stop_id' => $destinationPhysical->id,
        'name' => 'Destination',
        'city' => 'Parakou',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 420,
        'status' => 'active',
    ]);

    $bus = Bus::query()->create([
        'uuid' => Str::uuid()->toString(),
        'registration_number' => 'PE-'.Str::random(6),
        'brand' => 'KORA',
        'model' => 'Coach PE',
        'seat_count' => 4,
        'status' => 'active',
    ]);

    $seats = collect(range(1, 4))->map(fn (int $i) => Seat::query()->create([
        'uuid' => Str::uuid()->toString(),
        'bus_id' => $bus->id,
        'code' => "{$i}A",
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]));

    $departureAt = CarbonImmutable::parse('2026-09-01 08:00:00')->addHours((int) round($hoursFromNow));

    $departure = Departure::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => $departureAt->toDateString(),
        'departure_time' => $departureAt->toTimeString(),
        'estimated_arrival_at' => $departureAt->addHours(6),
        'status' => 'scheduled',
    ]);

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 10000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    if ($occupiedSeats > 0) {
        $reservation = Reservation::query()->create([
            'uuid' => Str::uuid()->toString(),
            'status' => 'confirmed',
            'total_amount' => 10000,
            'currency' => 'XOF',
            'channel' => 'online',
        ]);

        $seats->take($occupiedSeats)->each(fn (Seat $seat) => ReservationSegment::query()->create([
            'uuid' => Str::uuid()->toString(),
            'reservation_id' => $reservation->id,
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $destination->stop_order,
            'status' => 'confirmed',
        ]));
    }

    $user = User::factory()->create();

    return compact('user', 'departure', 'origin', 'destination');
}

it('applies a pricing rule only when the fill rate condition is met', function (): void {
    // 3 sièges occupés sur 4 vendables => 75% de remplissage.
    $scenario = pricingEngineScenario(occupiedSeats: 3, hoursFromNow: 200);

    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => ['fill_rate_min' => 70],
    ]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/quotes', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ])->assertCreated()
        ->assertJsonPath('data.coefficient', 1.2)
        ->assertJsonPath('data.final_amount', 12000);
});

it('does not apply a pricing rule when the fill rate condition is not met', function (): void {
    // 1 siège occupé sur 4 vendables => 25% de remplissage, sous le seuil de 70%.
    $scenario = pricingEngineScenario(occupiedSeats: 1, hoursFromNow: 200);

    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => ['fill_rate_min' => 70],
    ]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/quotes', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ])->assertCreated()
        ->assertJsonPath('data.coefficient', 1.0)
        ->assertJsonPath('data.final_amount', 10000);
});

it('applies a pricing rule based on the delay before departure, regardless of fill rate', function (): void {
    // Aucun siège occupé, mais départ dans 10h (< seuil de 24h) => la règle s'applique quand même.
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 10);

    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'type' => 'increase',
        'percentage' => 30.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => ['hours_before_departure_max' => 24],
    ]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/quotes', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ])->assertCreated()
        ->assertJsonPath('data.coefficient', 1.3);
});

it('does not apply a delay-based rule when the departure is too far away', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 200);

    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'type' => 'increase',
        'percentage' => 30.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => ['hours_before_departure_max' => 24],
    ]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/quotes', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ])->assertCreated()
        ->assertJsonPath('data.coefficient', 1.0);
});

it('clamps the final coefficient to the configured ceiling', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 200);

    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'type' => 'increase',
        'percentage' => 200.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => [],
    ]);

    PricingSetting::query()->create(['ceiling_coefficient' => 2.0]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/quotes', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ])->assertCreated()
        // Sans plafond le coefficient serait 3.0 (1 + 200%) — borné à 2.0.
        ->assertJsonPath('data.coefficient', 2.0)
        ->assertJsonPath('data.final_amount', 20000);
});

it('clamps the final coefficient to the configured floor', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 200);

    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'type' => 'decrease',
        'percentage' => 90.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => [],
    ]);

    PricingSetting::query()->create(['floor_coefficient' => 0.5]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/quotes', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ])->assertCreated()
        // Sans plancher le coefficient serait 0.1 (1 - 90%) — borné à 0.5.
        ->assertJsonPath('data.coefficient', 0.5)
        ->assertJsonPath('data.final_amount', 5000);
});

it('picks the highest priority matching rule among several active candidates', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 3, hoursFromNow: 200);

    // Priorité 20 : ne matche pas (remplissage insuffisant pour ce seuil à 90%).
    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'type' => 'increase',
        'percentage' => 50.0,
        'priority' => 5,
        'status' => 'active',
        'conditions' => ['fill_rate_min' => 90],
    ]);

    // Priorité 10 : matche (remplissage réel 75% >= 70%).
    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => ['fill_rate_min' => 70],
    ]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/quotes', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ])->assertCreated()
        ->assertJsonPath('data.coefficient', 1.2);
});

it('exposes the base fare in the departure fare preview (dynamic pricing applied at quote time)', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 3, hoursFromNow: 10);

    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['departure']->line_id,
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => ['fill_rate_min' => 70, 'hours_before_departure_max' => 24],
    ]);

    $this->getJson('/api/v1/bus/departures?date='.$scenario['departure']->departure_date->toDateString().'&from=Cotonou&to=Parakou')
        ->assertOk()
        ->assertJsonPath('data.0.fare_preview.base_amount', 10000)
        ->assertJsonPath('data.0.fare_preview.final_amount', 10000)
        ->assertJsonPath('data.0.fare_preview.is_quote', false);
});
