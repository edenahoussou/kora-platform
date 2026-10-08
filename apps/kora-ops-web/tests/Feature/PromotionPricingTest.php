<?php

use App\Models\User;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Services\GroupPricingService;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Actions\RecordPromotionRedemptionAction;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\Promotion;
use App\Modules\DynamicPricing\Models\PromotionRedemption;
use App\Modules\Settings\Settings\OperationsSettings;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

it('applies a percentage promotion on every eligible leg and persists the quote contract', function (): void {
    $scenario = promotionItineraryScenario();
    Sanctum::actingAs($scenario['user']);
    promotion(['code' => 'NORD10']);

    $quote = $this->postJson('/api/v1/bus/itinerary-quotes', [
        'promo_code' => 'nord10',
        'passenger_count' => 1,
        'legs' => promotionLegs($scenario),
    ])->assertOk();

    $quote->assertJsonPath('data.pricing.promotion.code', 'NORD10')
        ->assertJsonPath('data.pricing.promotion_discount_amount', 1500)
        ->assertJsonPath('data.total_amount', 13500);

    $itineraryQuote = ItineraryQuote::query()
        ->where('uuid', $quote->json('data.itinerary_quote_uuid'))
        ->firstOrFail();

    expect($itineraryQuote->promotion_id)->not->toBeNull()
        ->and($itineraryQuote->promotion_discount_amount)->toBe(1500)
        ->and($itineraryQuote->snapshot['promotion']['code'])->toBe('NORD10')
        ->and($itineraryQuote->snapshot['pricing_breakdown']['promotions'])->toHaveCount(1);

    // Le devis ne consomme jamais d'usage : la rédemption attend le paiement.
    expect(PromotionRedemption::query()->count())->toBe(0)
        ->and(Promotion::query()->where('code', 'NORD10')->firstOrFail()->used_count)->toBe(0);
});

it('deducts a fixed-amount promotion once from the reservation total', function (): void {
    $scenario = promotionItineraryScenario();
    Sanctum::actingAs($scenario['user']);
    promotion(['code' => 'FIXE2K', 'benefit_type' => 'fixed_amount', 'benefit_value' => 2000]);

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'promo_code' => 'FIXE2K',
        'legs' => promotionLegs($scenario),
    ])->assertOk()
        ->assertJsonPath('data.pricing.promotion_discount_amount', 2000)
        ->assertJsonPath('data.total_amount', 13000);
});

it('only discounts the legs covered by a line-scoped promotion', function (): void {
    $scenario = promotionItineraryScenario();
    Sanctum::actingAs($scenario['user']);
    promotion([
        'code' => 'LIGNE10',
        'scope_type' => 'line',
        'line_id' => $scenario['firstDeparture']->line_id,
    ]);

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'promo_code' => 'LIGNE10',
        'legs' => promotionLegs($scenario),
    ])->assertOk()
        ->assertJsonPath('data.pricing.promotion_discount_amount', 1000)
        ->assertJsonPath('data.total_amount', 14000);
});

it('rejects a counter-scoped promotion on the mobile channel', function (): void {
    $scenario = promotionItineraryScenario();
    Sanctum::actingAs($scenario['user']);
    promotion(['code' => 'GUICHET', 'scope_type' => 'channel', 'channel' => 'counter']);

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'promo_code' => 'GUICHET',
        'legs' => promotionLegs($scenario),
    ])->assertUnprocessable()->assertJsonValidationErrors('promo_code');
});

it('rejects unknown, scheduled, expired or exhausted codes', function (string $code, array $overrides): void {
    $scenario = promotionItineraryScenario();
    Sanctum::actingAs($scenario['user']);
    if ($overrides !== []) {
        promotion(['code' => $code, ...$overrides]);
    }

    $this->postJson('/api/v1/bus/itinerary-quotes', [
        'promo_code' => $code,
        'legs' => promotionLegs($scenario),
    ])->assertUnprocessable()->assertJsonValidationErrors('promo_code');
})->with([
    'unknown code' => ['INCONNU', []],
    'scheduled promotion' => ['FUTURE', ['starts_at' => now()->addWeek()]],
    'expired promotion' => ['PASSEE', ['ends_at' => now()->subDay()]],
    'inactive promotion' => ['OFFLINE', ['status' => 'inactive']],
    'exhausted promotion' => ['EPUISEE', ['max_uses' => 1, 'used_count' => 1]],
]);

it('calculates the group discount on the post-promotion subtotal', function (): void {
    GroupBookingSetting::query()->create(['minimum_passengers' => 5, 'maximum_passengers' => 10]);
    $settings = app(OperationsSettings::class);
    $settings->groupDiscountEnabled = true;
    $settings->groupDiscountPassengerThreshold = 10;
    $settings->groupDiscountPercentage = 10;
    $settings->save();

    $pricing = app(GroupPricingService::class)->calculate(
        unitAmounts: [10000, 5000],
        passengerCount: 10,
        currency: 'XOF',
        promotionDiscount: 15000,
    );

    expect($pricing['subtotal_amount'])->toBe(150000)
        ->and($pricing['promotion_discount_amount'])->toBe(15000)
        ->and($pricing['group_discount']['amount'])->toBe(13500)
        ->and($pricing['total_amount'])->toBe(121500)
        ->and(array_sum($pricing['passenger_allocations']))->toBe(121500);
});

it('records one redemption when the reservation is paid and stays idempotent', function (): void {
    $scenario = promotionItineraryScenario();
    $promotion = promotion(['code' => 'PAID10', 'benefit_type' => 'percentage', 'benefit_value' => 10]);
    $quote = ItineraryQuote::query()->create([
        'user_id' => $scenario['user']->id,
        'passenger_count' => 1,
        'subtotal_amount' => 15000,
        'promotion_id' => $promotion->id,
        'promotion_discount_amount' => 1500,
        'total_amount' => 13500,
        'currency' => 'XOF',
        'snapshot' => ['type' => 'itinerary'],
        'expires_at' => now()->addMinutes(10),
    ]);
    $reservation = paidReservation($scenario['user'], $quote);

    $action = app(RecordPromotionRedemptionAction::class);
    $first = $action->execute($reservation);
    $second = $action->execute($reservation->refresh());

    expect($first)->not->toBeNull()
        ->and($second?->getKey())->toBe($first?->getKey())
        ->and(PromotionRedemption::query()->count())->toBe(1)
        ->and($promotion->refresh()->used_count)->toBe(1)
        ->and($first?->discount_amount)->toBe(1500);
});

it('does not record a redemption once the usage limit is reached', function (): void {
    $scenario = promotionItineraryScenario();
    $promotion = promotion(['code' => 'FULL', 'max_uses' => 1, 'used_count' => 1]);
    $quote = ItineraryQuote::query()->create([
        'user_id' => $scenario['user']->id,
        'passenger_count' => 1,
        'subtotal_amount' => 15000,
        'promotion_id' => $promotion->id,
        'promotion_discount_amount' => 1500,
        'total_amount' => 13500,
        'currency' => 'XOF',
        'snapshot' => ['type' => 'itinerary'],
        'expires_at' => now()->addMinutes(10),
    ]);

    expect(app(RecordPromotionRedemptionAction::class)->execute(paidReservation($scenario['user'], $quote)))
        ->toBeNull()
        ->and(PromotionRedemption::query()->count())->toBe(0);
});

it('ignores a paid reservation whose quote carries no promotion', function (): void {
    $scenario = promotionItineraryScenario();
    $quote = ItineraryQuote::query()->create([
        'user_id' => $scenario['user']->id,
        'passenger_count' => 1,
        'subtotal_amount' => 15000,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'snapshot' => ['type' => 'itinerary'],
        'expires_at' => now()->addMinutes(10),
    ]);

    expect(app(RecordPromotionRedemptionAction::class)->execute(paidReservation($scenario['user'], $quote)))
        ->toBeNull()
        ->and(PromotionRedemption::query()->count())->toBe(0);
});

/** @param array<string, mixed> $overrides */
function promotion(array $overrides = []): Promotion
{
    return Promotion::query()->create([
        'name' => 'Promotion test',
        'code' => 'TESTPROMO',
        'benefit_type' => 'percentage',
        'benefit_value' => 10,
        'scope_type' => 'network',
        'priority' => 10,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        ...$overrides,
    ]);
}

/** @return list<array<string, string>> */
function promotionLegs(array $scenario): array
{
    return [[
        'departure_uuid' => $scenario['firstDeparture']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['connection']->uuid,
    ], [
        'departure_uuid' => $scenario['secondDeparture']->uuid,
        'origin_stop_uuid' => $scenario['secondConnection']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ]];
}

function paidReservation(User $user, ItineraryQuote $quote): Reservation
{
    return Reservation::query()->create([
        'payer_user_id' => $user->id,
        'itinerary_quote_id' => $quote->id,
        'status' => 'paid',
        'total_amount' => $quote->total_amount,
        'currency' => 'XOF',
        'channel' => 'mobile',
        'idempotency_key' => 'reservation-'.fake()->uuid(),
        'expires_at' => now()->addMinutes(15),
        'total_snapshot' => ['passenger_allocations' => [$quote->total_amount]],
    ]);
}

/** @return array<string, mixed> */
function promotionItineraryScenario(): array
{
    $departureDate = now()->addDays(7)->toDateString();
    $user = User::factory()->create();
    $originPhysical = PhysicalStop::create(['name' => 'Promo Origin', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $connectionPhysical = PhysicalStop::create(['name' => 'Promo Connection', 'city' => 'Lome', 'country' => 'TG', 'status' => 'active']);
    $destinationPhysical = PhysicalStop::create(['name' => 'Promo Destination', 'city' => 'Accra', 'country' => 'GH', 'status' => 'active']);

    $firstLine = Line::create(['code' => 'PR-L1', 'name' => 'Cotonou-Lome', 'countries_crossed' => ['BJ'], 'status' => 'active']);
    $secondLine = Line::create(['code' => 'PR-L2', 'name' => 'Lome-Accra', 'countries_crossed' => ['BJ'], 'status' => 'active']);

    $origin = Stop::create(['line_id' => $firstLine->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Origin', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
    $connection = Stop::create(['line_id' => $firstLine->id, 'physical_stop_id' => $connectionPhysical->id, 'name' => 'Connection A', 'city' => 'Lome', 'country' => 'TG', 'stop_order' => 2, 'status' => 'active']);
    $secondConnection = Stop::create(['line_id' => $secondLine->id, 'physical_stop_id' => $connectionPhysical->id, 'name' => 'Connection B', 'city' => 'Lome', 'country' => 'TG', 'stop_order' => 1, 'status' => 'active']);
    $destination = Stop::create(['line_id' => $secondLine->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Destination', 'city' => 'Accra', 'country' => 'GH', 'stop_order' => 2, 'status' => 'active']);

    $firstBus = Bus::create(['registration_number' => 'PR-BUS-1', 'brand' => 'KORA', 'model' => 'Test', 'seat_count' => 1, 'status' => 'active']);
    $secondBus = Bus::create(['registration_number' => 'PR-BUS-2', 'brand' => 'KORA', 'model' => 'Test', 'seat_count' => 1, 'status' => 'active']);
    Seat::create(['bus_id' => $firstBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    Seat::create(['bus_id' => $secondBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

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

    return compact('user', 'origin', 'connection', 'secondConnection', 'destination', 'firstDeparture', 'secondDeparture', 'departureDate');
}
