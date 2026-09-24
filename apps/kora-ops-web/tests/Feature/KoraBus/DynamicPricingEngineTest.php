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
 * @return array{user: User, departure: Departure, origin: Stop, destination: Stop, line: Line}
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

    return compact('user', 'departure', 'origin', 'destination', 'line');
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

    $rule = PricingRule::query()->where('percentage', 20.0)->firstOrFail();

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.base_amount', 10000)
        ->assertJsonPath('data.legs.0.coefficient', 1.20)
        ->assertJsonPath('data.legs.0.final_amount', 12000)
        ->assertJsonPath('data.legs.0.dynamic_adjustment_amount', 2000)
        ->assertJsonPath('data.legs.0.pricing_rule_uuid', $rule->uuid)
        ->assertJsonPath('data.legs.0.fill_rate', 75)
        ->assertJsonPath('data.pricing.dynamic_adjustment_amount', 2000);
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

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.base_amount', 10000)
        ->assertJsonPath('data.legs.0.coefficient', 1)
        ->assertJsonPath('data.legs.0.final_amount', 10000)
        ->assertJsonPath('data.legs.0.dynamic_adjustment_amount', 0)
        ->assertJsonPath('data.legs.0.pricing_rule_uuid', null)
        ->assertJsonPath('data.pricing.dynamic_adjustment_amount', 0);
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

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.final_amount', 13000)
        ->assertJsonPath('data.pricing.dynamic_adjustment_amount', 3000);
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

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.final_amount', 10000)
        ->assertJsonPath('data.pricing.dynamic_adjustment_amount', 0);
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

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        // Sans plafond le coefficient serait 3.0 (1 + 200%) — borné à 2.0.
        ->assertJsonPath('data.legs.0.final_amount', 20000)
        ->assertJsonPath('data.pricing.dynamic_adjustment_amount', 10000);
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

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        // Sans plancher le coefficient serait 0.1 (1 - 90%) — borné à 0.5.
        ->assertJsonPath('data.legs.0.final_amount', 5000)
        ->assertJsonPath('data.pricing.dynamic_adjustment_amount', -5000);
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

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.final_amount', 12000)
        ->assertJsonPath('data.pricing.dynamic_adjustment_amount', 2000);
});

it('exposes the dynamic price in the itinerary search fare preview', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 3, hoursFromNow: 10);

    $rule = PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['departure']->line_id,
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => ['fill_rate_min' => 70, 'hours_before_departure_max' => 24],
    ]);

    $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date='.$scenario['departure']->departure_date->toDateString())
        ->assertOk()
        ->assertJsonPath('data.0.total_amount', 12000)
        ->assertJsonPath('data.0.legs.0.amount', 12000)
        ->assertJsonPath('data.0.legs.0.base_amount', 10000)
        ->assertJsonPath('data.0.legs.0.coefficient', 1.2)
        ->assertJsonPath('data.0.legs.0.final_amount', 12000)
        ->assertJsonPath('data.0.legs.0.dynamic_adjustment_amount', 2000)
        ->assertJsonPath('data.0.legs.0.pricing_rule_uuid', $rule->uuid)
        ->assertJsonPath('data.0.legs.0.fill_rate', 75);
});

it('returns neutral dynamic pricing fields when no rule matches in search', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 200);

    $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date='.$scenario['departure']->departure_date->toDateString())
        ->assertOk()
        ->assertJsonPath('data.0.total_amount', 10000)
        ->assertJsonPath('data.0.legs.0.amount', 10000)
        ->assertJsonPath('data.0.legs.0.base_amount', 10000)
        ->assertJsonPath('data.0.legs.0.coefficient', 1)
        ->assertJsonPath('data.0.legs.0.final_amount', 10000)
        ->assertJsonPath('data.0.legs.0.dynamic_adjustment_amount', 0)
        ->assertJsonPath('data.0.legs.0.pricing_rule_uuid', null);
});

it('applies a network-scoped rule to any line', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 200);

    $rule = PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'scope_type' => 'network',
        'line_id' => null,
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => [],
    ]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.final_amount', 12000)
        ->assertJsonPath('data.legs.0.pricing_rule_uuid', $rule->uuid);
});

it('applies a segment-scoped rule to its exact O/D journey', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 200);

    $rule = PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'scope_type' => 'segment',
        'line_id' => $scenario['line']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => [],
    ]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.final_amount', 12000)
        ->assertJsonPath('data.legs.0.pricing_rule_uuid', $rule->uuid);
});

it('does not apply a segment-scoped rule to another O/D journey on the same line', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 200);

    $thirdPhysical = PhysicalStop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'name' => 'Troisième Physical',
        'city' => 'Djougou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $third = Stop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['line']->id,
        'physical_stop_id' => $thirdPhysical->id,
        'name' => 'Troisième',
        'city' => 'Djougou',
        'country' => 'BJ',
        'stop_order' => 3,
        'km_from_origin' => 500,
        'status' => 'active',
    ]);

    // Horaires publiés requis pour coter la jambe Destination→Troisième.
    $scenario['departure']->update(['stop_times' => [
        ['stop_uuid' => $scenario['origin']->uuid, 'departure_minutes' => 0, 'arrival_minutes' => 0],
        ['stop_uuid' => $scenario['destination']->uuid, 'departure_minutes' => 300, 'arrival_minutes' => 300],
        ['stop_uuid' => $third->uuid, 'departure_minutes' => 360, 'arrival_minutes' => 360],
    ]]);

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['line']->id,
        'origin_stop_id' => $scenario['destination']->id,
        'destination_stop_id' => $third->id,
        'base_amount' => 5000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    // Règle scopée au segment Origine→Destination uniquement.
    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'scope_type' => 'segment',
        'line_id' => $scenario['line']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => [],
    ]);

    Sanctum::actingAs($scenario['user']);

    // Trajet Destination→Troisième : hors du segment de la règle → pas de variation.
    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['destination']->uuid,
            'destination_stop_uuid' => $third->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.final_amount', 5000)
        ->assertJsonPath('data.legs.0.pricing_rule_uuid', null);
});

it('applies a segment rule to its O/D pair regardless of stored stop order when direction is both', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 200);

    // Les arrêts de la règle sont enregistrés dans l'ordre inverse : la paire
    // O/D reste la même, direction « les deux sens » → la règle s'applique.
    $rule = PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'scope_type' => 'segment',
        'line_id' => $scenario['line']->id,
        'origin_stop_id' => $scenario['destination']->id,
        'destination_stop_id' => $scenario['origin']->id,
        'direction' => 'both',
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => [],
    ]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.final_amount', 12000)
        ->assertJsonPath('data.legs.0.pricing_rule_uuid', $rule->uuid);
});

it('does not apply a return-direction rule to an outbound journey', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 200);

    // Trajet Origine(1)→Destination(2) = sens aller ; la règle « retour »
    // ne doit pas s'appliquer.
    PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'scope_type' => 'line',
        'line_id' => $scenario['line']->id,
        'direction' => 'return',
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => [],
    ]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.final_amount', 10000)
        ->assertJsonPath('data.legs.0.pricing_rule_uuid', null);
});

it('applies an outbound-direction rule to an outbound journey', function (): void {
    $scenario = pricingEngineScenario(occupiedSeats: 0, hoursFromNow: 200);

    $rule = PricingRule::query()->create([
        'uuid' => Str::uuid()->toString(),
        'scope_type' => 'line',
        'line_id' => $scenario['line']->id,
        'direction' => 'outbound',
        'type' => 'increase',
        'percentage' => 20.0,
        'priority' => 10,
        'status' => 'active',
        'conditions' => [],
    ]);

    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => [[
            'leg_order' => 1,
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.legs.0.final_amount', 12000)
        ->assertJsonPath('data.legs.0.pricing_rule_uuid', $rule->uuid);
});
