<?php

use App\Models\User;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\CompanyAccounts\Data\CompanyDiscountType;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\Settings\Settings\OperationsSettings;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

it('searches, quotes and holds a two-leg itinerary without trusting client pricing', function (): void {
    $scenario = itineraryTestScenario();
    Sanctum::actingAs($scenario['user']);

    $search = $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['originPhysical']->uuid
        .'&to_physical_stop_uuid='.$scenario['destinationPhysical']->uuid
        .'&date='.$scenario['departureDate'].'&passenger_count=1');

    $search->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.legs.0.departure_at', $scenario['departureDate'].'T08:00:00+01:00')
        ->assertJsonPath('data.0.legs.0.arrival_at', $scenario['departureDate'].'T09:00:00+00:00')
        ->assertJsonPath('data.0.legs.0.departure_timezone', 'Africa/Porto-Novo')
        ->assertJsonPath('data.0.legs.0.arrival_timezone', 'Africa/Lome');
    $legs = $search->json('data.0.legs');
    expect($legs)->toHaveCount(2);

    $quote = $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 1,
        'legs' => array_map(fn (array $leg): array => [
            'leg_order' => $leg['leg_order'],
            'departure_uuid' => $leg['departure_uuid'],
            'origin_stop_uuid' => $leg['origin_stop']['uuid'],
            'destination_stop_uuid' => $leg['destination_stop']['uuid'],
        ], $legs),
    ])->assertOk();

    $quote->assertJsonPath('data.pricing.total_amount', 15000)
        ->assertJsonMissingPath('data.snapshot');

    $quoteUuid = $quote->json('data.itinerary_quote_uuid');
    expect($quoteUuid)->not->toBeNull();

    $hold = $this->postJson('/api/v1/bus/itinerary-holds', [
        'itinerary_quote_uuid' => $quoteUuid,
        'idempotency_key' => 'itinerary-test-1',
        'total_amount' => 1,
        'passengers' => [[
            'passenger_index' => 0,
            'seat_assignments' => [
                ['leg_order' => 1, 'seat_uuid' => $scenario['firstSeat']->uuid],
                ['leg_order' => 2, 'seat_uuid' => $scenario['secondSeat']->uuid],
            ],
        ]],
    ])->assertCreated();

    $hold->assertJsonPath('data.total_amount', 15000)
        ->assertJsonCount(2, 'data.segments')
        ->assertJsonPath('data.segments.0.passenger_index', 0)
        ->assertJsonPath('data.segments.1.passenger_index', 0);

    $reservationUuid = $hold->json('data.uuid');
    $this->postJson("/api/v1/bookings/{$reservationUuid}/passengers", [
        'passengers' => [[
            'passenger_index' => 0,
            'first_name' => 'Aïcha',
            'last_name' => 'HOUNKPÈ',
            'phone' => '+2290197421860',
        ]],
    ])->assertOk();

    $reservation = Reservation::query()->where('uuid', $hold->json('data.uuid'))->firstOrFail();
    $passenger = ReservationPassenger::query()->where('reservation_id', $reservation->id)->first();
    expect($passenger)->not->toBeNull();
    expect(ReservationSegment::query()->where('reservation_passenger_id', $passenger?->id)->count())->toBe(2);
});

it('returns the same itinerary hold for a repeated idempotency key', function (): void {
    $scenario = itineraryTestScenario();
    Sanctum::actingAs($scenario['user']);

    $quoteUuid = $this->postJson('/api/v1/bus/itinerary-quotes', [
        'legs' => [[
            'departure_uuid' => $scenario['firstDeparture']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['connection']->uuid,
        ], [
            'departure_uuid' => $scenario['secondDeparture']->uuid,
            'origin_stop_uuid' => $scenario['secondConnection']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ])->assertOk()->json('data.itinerary_quote_uuid');

    $payload = [
        'itinerary_quote_uuid' => $quoteUuid,
        'idempotency_key' => 'itinerary-test-repeat',
        'passengers' => [[
            'passenger_index' => 0,
            'seat_assignments' => [
                ['leg_order' => 1, 'seat_uuid' => $scenario['firstSeat']->uuid],
                ['leg_order' => 2, 'seat_uuid' => $scenario['secondSeat']->uuid],
            ],
        ]],
    ];

    $first = $this->postJson('/api/v1/bus/itinerary-holds', $payload)->assertCreated();
    $second = $this->postJson('/api/v1/bus/itinerary-holds', $payload)->assertCreated();

    expect($second->json('data.uuid'))->toBe($first->json('data.uuid'));
    expect(Reservation::query()->where('idempotency_key', 'itinerary-test-repeat')->count())->toBe(1);
});

it('reprices an api quote with the negotiated discount when the hold uses a company payer', function (): void {
    $scenario = itineraryTestScenario();
    $account = companyApiAccount($scenario['user']);
    $extraSeatA = Seat::create(['bus_id' => $scenario['firstSeat']->bus_id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $extraSeatB = Seat::create(['bus_id' => $scenario['secondSeat']->bus_id, 'code' => '1B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    Sanctum::actingAs($scenario['user']);

    $legs = [[
        'departure_uuid' => $scenario['firstDeparture']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['connection']->uuid,
    ], [
        'departure_uuid' => $scenario['secondDeparture']->uuid,
        'origin_stop_uuid' => $scenario['secondConnection']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ]];

    $quoteUuid = $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 2,
        'legs' => $legs,
    ])->assertOk()
        ->assertJsonPath('data.pricing.total_amount', 27000)
        ->assertJsonPath('data.pricing.group_discount.applied', true)
        ->json('data.itinerary_quote_uuid');

    $hold = $this->postJson('/api/v1/bus/itinerary-holds', [
        'itinerary_quote_uuid' => $quoteUuid,
        'idempotency_key' => 'itinerary-company-1',
        'passengers' => [[
            'passenger_index' => 0,
            'seat_assignments' => [
                ['leg_order' => 1, 'seat_uuid' => $scenario['firstSeat']->uuid],
                ['leg_order' => 2, 'seat_uuid' => $scenario['secondSeat']->uuid],
            ],
        ], [
            'passenger_index' => 1,
            'seat_assignments' => [
                ['leg_order' => 1, 'seat_uuid' => $extraSeatA->uuid],
                ['leg_order' => 2, 'seat_uuid' => $extraSeatB->uuid],
            ],
        ]],
        'payer' => [
            'type' => 'company',
            'company_account_uuid' => $account->uuid,
        ],
    ])->assertCreated();

    $hold->assertJsonPath('data.total_amount', 24000);

    $reservation = Reservation::query()->where('uuid', $hold->json('data.uuid'))->firstOrFail();
    $snapshot = $reservation->total_snapshot;
    expect($snapshot['group_discount']['applied'])->toBeFalse()
        ->and($snapshot['company_account_uuid'])->toBe($account->uuid)
        ->and($snapshot['company_discount'])->toEqual([
            'applied' => true,
            'type' => 'percentage',
            'value' => 20.0,
            'amount' => 6000,
        ]);
});

it('applies the negotiated company discount on the api quote when a company payer is provided', function (): void {
    $scenario = itineraryTestScenario();
    $account = companyApiAccount($scenario['user']);
    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'passenger_count' => 2,
        'legs' => [[
            'departure_uuid' => $scenario['firstDeparture']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['connection']->uuid,
        ], [
            'departure_uuid' => $scenario['secondDeparture']->uuid,
            'origin_stop_uuid' => $scenario['secondConnection']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
        'payer' => [
            'type' => 'company',
            'company_account_uuid' => $account->uuid,
        ],
    ])->assertOk()
        ->assertJsonPath('data.pricing.total_amount', 24000)
        ->assertJsonPath('data.pricing.group_discount.applied', false)
        ->assertJsonPath('data.pricing.company_discount.applied', true)
        ->assertJsonPath('data.pricing.company_discount.amount', 6000);
});

it('rejects an itinerary quote with disconnected legs', function (): void {
    $scenario = itineraryTestScenario();
    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'legs' => [[
            'departure_uuid' => $scenario['firstDeparture']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['connection']->uuid,
        ], [
            'departure_uuid' => $scenario['secondDeparture']->uuid,
            'origin_stop_uuid' => $scenario['destination']->uuid,
            'destination_stop_uuid' => $scenario['secondConnection']->uuid,
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['legs.1.destination_stop_uuid']);
});

/** @return array<string, mixed> */
function itineraryTestScenario(): array
{
    $departureDate = now()->addDays(7)->toDateString();
    $user = User::factory()->create();
    $originPhysical = PhysicalStop::create(['name' => 'Itinerary Origin', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $connectionPhysical = PhysicalStop::create(['name' => 'Itinerary Connection', 'city' => 'Lome', 'country' => 'TG', 'status' => 'active']);
    $destinationPhysical = PhysicalStop::create(['name' => 'Itinerary Destination', 'city' => 'Accra', 'country' => 'GH', 'status' => 'active']);

    $firstLine = Line::create(['code' => 'IT-L1', 'name' => 'Cotonou-Lome', 'countries_crossed' => ['BJ'], 'status' => 'active']);
    $secondLine = Line::create(['code' => 'IT-L2', 'name' => 'Lome-Accra', 'countries_crossed' => ['BJ'], 'status' => 'active']);

    $origin = Stop::create(['line_id' => $firstLine->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Origin', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
    $connection = Stop::create(['line_id' => $firstLine->id, 'physical_stop_id' => $connectionPhysical->id, 'name' => 'Connection A', 'city' => 'Lome', 'country' => 'TG', 'stop_order' => 2, 'status' => 'active']);
    $secondConnection = Stop::create(['line_id' => $secondLine->id, 'physical_stop_id' => $connectionPhysical->id, 'name' => 'Connection B', 'city' => 'Lome', 'country' => 'TG', 'stop_order' => 1, 'status' => 'active']);
    $destination = Stop::create(['line_id' => $secondLine->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Destination', 'city' => 'Accra', 'country' => 'GH', 'stop_order' => 2, 'status' => 'active']);

    $firstBus = Bus::create(['registration_number' => 'IT-BUS-1', 'brand' => 'KORA', 'model' => 'Test', 'seat_count' => 1, 'status' => 'active']);
    $secondBus = Bus::create(['registration_number' => 'IT-BUS-2', 'brand' => 'KORA', 'model' => 'Test', 'seat_count' => 1, 'status' => 'active']);
    $firstSeat = Seat::create(['bus_id' => $firstBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $secondSeat = Seat::create(['bus_id' => $secondBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    $firstDeparture = Departure::create([
        'line_id' => $firstLine->id,
        'bus_id' => $firstBus->id,
        'departure_date' => $departureDate,
        'departure_time' => '08:00',
        'scheduled_at' => CarbonImmutable::parse($departureDate.' 08:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $origin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $connection->uuid, 'departure_minutes' => null, 'arrival_minutes' => 120, 'timezone' => 'Africa/Lome'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse($departureDate.' 10:00', 'Africa/Lome'),
        'status' => 'scheduled',
    ]);
    $secondDeparture = Departure::create([
        'line_id' => $secondLine->id,
        'bus_id' => $secondBus->id,
        'departure_date' => $departureDate,
        'departure_time' => '11:00',
        'scheduled_at' => CarbonImmutable::parse($departureDate.' 11:00', 'Africa/Lome'),
        'departure_timezone' => 'Africa/Lome',
        'stop_times' => [
            ['stop_uuid' => $secondConnection->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Lome'],
            ['stop_uuid' => $destination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 180, 'timezone' => 'Africa/Accra'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse($departureDate.' 14:00', 'Africa/Accra'),
        'status' => 'scheduled',
    ]);

    Fare::create(['line_id' => $firstLine->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $connection->id, 'base_amount' => 10000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);
    Fare::create(['line_id' => $secondLine->id, 'origin_stop_id' => $secondConnection->id, 'destination_stop_id' => $destination->id, 'base_amount' => 5000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);

    return compact('user', 'originPhysical', 'connectionPhysical', 'destinationPhysical', 'origin', 'connection', 'secondConnection', 'destination', 'firstDeparture', 'secondDeparture', 'firstSeat', 'secondSeat', 'departureDate');
}

function companyApiAccount(User $user): CompanyAccount
{
    GroupBookingSetting::query()->create(['minimum_passengers' => 2, 'maximum_passengers' => 10]);
    $settings = app(OperationsSettings::class);
    $settings->groupDiscountEnabled = true;
    $settings->groupDiscountPassengerThreshold = 2;
    $settings->groupDiscountPercentage = 10;
    $settings->save();

    $account = CompanyAccount::query()->create([
        'name' => 'ACME API',
        'type' => 'company',
        'reference' => 'ACME-API',
        'monthly_credit_limit' => 5000000,
        'status' => 'active',
        'discount_type' => CompanyDiscountType::Percentage,
        'discount_value' => 20,
    ]);
    CompanyAccountMember::query()->create([
        'company_account_id' => $account->id,
        'user_id' => $user->id,
        'role' => 'member',
        'status' => 'active',
    ]);

    return $account;
}
