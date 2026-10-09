<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
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
use App\Modules\Fiscalization\Services\FiscalInvoicePayloadBuilder;
use App\Modules\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

it('applies a promo code on a held reservation and recomputes the total', function (): void {
    $scenario = promoBookingScenario();
    $promotion = bookingPromotion(['code' => 'TEST10', 'benefit_value' => 10]);
    $reservationUuid = heldPromoBooking($this, $scenario);

    $response = $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", [
        'promo_code' => 'test10',
    ])->assertOk();

    $response->assertJsonPath('data.status', 'held')
        ->assertJsonPath('data.total_amount', 9000)
        ->assertJsonPath('data.subtotal_amount', 10000)
        ->assertJsonPath('data.promotion_discount_amount', 1000)
        ->assertJsonPath('data.promotion.code', 'TEST10')
        ->assertJsonPath('data.promotion.discount_amount', 1000);

    $reservation = Reservation::query()->where('uuid', $reservationUuid)->firstOrFail();
    expect($reservation->promotion_id)->toBe($promotion->id)
        ->and($reservation->promotion_discount_amount)->toBe(1000)
        ->and($reservation->total_snapshot['promotion']['code'])->toBe('TEST10')
        ->and($reservation->total_snapshot['promotion_discount_amount'])->toBe(1000)
        ->and(array_sum($reservation->total_snapshot['passenger_allocations']))->toBe(9000);

    // Appliquer le code ne consomme jamais d'usage : la rédemption attend le paiement.
    expect(PromotionRedemption::query()->count())->toBe(0)
        ->and($promotion->refresh()->used_count)->toBe(0);
});

it('is idempotent when the same promo code is applied twice', function (): void {
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10', 'benefit_value' => 10]);
    $reservationUuid = heldPromoBooking($this, $scenario);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])->assertOk();
    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])->assertOk()
        ->assertJsonPath('data.total_amount', 9000)
        ->assertJsonPath('data.promotion.code', 'TEST10');
});

it('removes the promo code and restores the original total', function (): void {
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10', 'benefit_value' => 10]);
    $reservationUuid = heldPromoBooking($this, $scenario);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])->assertOk();
    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => null])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 10000)
        ->assertJsonPath('data.promotion_discount_amount', 0)
        ->assertJsonPath('data.promotion', null);

    $reservation = Reservation::query()->where('uuid', $reservationUuid)->firstOrFail();
    expect($reservation->promotion_id)->toBeNull()
        ->and($reservation->promotion_discount_amount)->toBe(0)
        ->and($reservation->total_snapshot['promotion'])->toBeNull();
});

it('replaces an applied promo code with another one', function (): void {
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10', 'benefit_value' => 10]);
    bookingPromotion(['code' => 'TEST20', 'benefit_value' => 20]);
    $reservationUuid = heldPromoBooking($this, $scenario);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])->assertOk();
    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST20'])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 8000)
        ->assertJsonPath('data.promotion.code', 'TEST20')
        ->assertJsonPath('data.promotion_discount_amount', 2000);
});

it('keeps the quote-level promo flow working by copying it onto the reservation', function (): void {
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10', 'benefit_value' => 10]);
    $reservationUuid = heldPromoBooking($this, $scenario, 'TEST10');

    $reservation = Reservation::query()->where('uuid', $reservationUuid)->firstOrFail();
    expect($reservation->total_amount)->toBe(9000)
        ->and($reservation->promotion?->code)->toBe('TEST10')
        ->and($reservation->promotion_discount_amount)->toBe(1000);
});

it('rejects invalid promo codes on a held reservation', function (string $code, array $overrides): void {
    $scenario = promoBookingScenario();
    if ($overrides !== []) {
        bookingPromotion(['code' => $code, ...$overrides]);
    }
    $reservationUuid = heldPromoBooking($this, $scenario);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => $code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('promo_code');

    expect(Reservation::query()->where('uuid', $reservationUuid)->firstOrFail()->total_amount)->toBe(10000);
})->with([
    'unknown code' => ['INCONNU', []],
    'scheduled promotion' => ['FUTURE', ['starts_at' => now()->addWeek()]],
    'expired promotion' => ['PASSEE', ['ends_at' => now()->subDay()]],
    'inactive promotion' => ['OFFLINE', ['status' => 'inactive']],
    'exhausted promotion' => ['EPUISEE', ['max_uses' => 1, 'used_count' => 1]],
    'counter channel only' => ['GUICHET', ['scope_type' => 'channel', 'channel' => 'counter']],
]);

it('rejects promo changes once the reservation is paid', function (): void {
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10']);
    $reservationUuid = heldPromoBooking($this, $scenario);

    Reservation::query()->where('uuid', $reservationUuid)->update(['status' => 'paid']);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('promo_code');
});

it('rejects promo changes while assistance is pending', function (): void {
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10']);
    $reservationUuid = heldPromoBooking($this, $scenario);

    Reservation::query()->where('uuid', $reservationUuid)->update(['status' => 'assistance_pending']);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('promo_code');
});

it('rejects promo changes on an expired hold', function (): void {
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10']);
    $reservationUuid = heldPromoBooking($this, $scenario);

    Reservation::query()->where('uuid', $reservationUuid)->update(['expires_at' => now()->subMinute()]);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('promo_code');
});

it('rejects promo changes while a payment is pending and allows them again once it failed', function (): void {
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10']);
    $reservationUuid = heldPromoBooking($this, $scenario);
    $reservation = Reservation::query()->where('uuid', $reservationUuid)->firstOrFail();

    $payment = Payment::query()->create([
        'reservation_id' => $reservation->id,
        'method' => 'mobile_money',
        'provider' => 'fedapay',
        'idempotency_key' => 'pay-'.fake()->uuid(),
        'amount' => 10000,
        'currency' => 'XOF',
        'status' => 'pending',
    ]);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('promo_code');

    $payment->update(['status' => 'failed']);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 9000);
});

it('forbids promo changes on a reservation owned by someone else', function (): void {
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10']);
    $reservationUuid = heldPromoBooking($this, $scenario);

    Sanctum::actingAs(User::factory()->create());

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])
        ->assertForbidden();
});

it('requires the promo_code key to be present', function (): void {
    $scenario = promoBookingScenario();
    $reservationUuid = heldPromoBooking($this, $scenario);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('promo_code');
});

it('records the redemption only once the reservation is paid', function (): void {
    $scenario = promoBookingScenario();
    $promotion = bookingPromotion(['code' => 'TEST10', 'benefit_value' => 10]);
    $reservationUuid = heldPromoBooking($this, $scenario);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])->assertOk();

    expect(PromotionRedemption::query()->count())->toBe(0);

    $reservation = Reservation::query()->where('uuid', $reservationUuid)->firstOrFail();
    $reservation->update(['status' => 'paid']);

    $action = app(RecordPromotionRedemptionAction::class);
    $first = $action->execute($reservation);
    $second = $action->execute($reservation->refresh());

    expect($first)->not->toBeNull()
        ->and($first?->discount_amount)->toBe(1000)
        ->and($first?->itinerary_quote_id)->toBe($reservation->itinerary_quote_id)
        ->and($second?->getKey())->toBe($first?->getKey())
        ->and(PromotionRedemption::query()->count())->toBe(1)
        ->and($promotion->refresh()->used_count)->toBe(1);
});

it('declares the promo discount explicitly in the fiscal invoice payload', function (): void {
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10', 'benefit_value' => 10]);
    $reservationUuid = heldPromoBooking($this, $scenario);

    $this->putJson("/api/v1/bookings/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])->assertOk();

    $reservation = Reservation::query()->where('uuid', $reservationUuid)->firstOrFail();
    ReservationPassenger::query()->create([
        'reservation_id' => $reservation->id,
        'passenger_index' => 0,
        'first_name' => 'Aïcha',
        'last_name' => 'HOUNKPÈ',
        'phone' => '+22901000000',
        'status' => 'active',
    ]);

    $payload = app(FiscalInvoicePayloadBuilder::class)->build($reservation->refresh());

    expect($payload['items'])->toHaveCount(1)
        ->and($payload['items'][0]['price'])->toBe(9000)
        ->and($payload['items'][0]['originalPrice'])->toBe(10000)
        ->and($payload['items'][0]['priceModification'])->toContain('TEST10');
});

it('omits the discount declaration when no promotion applies', function (): void {
    $scenario = promoBookingScenario();
    $reservationUuid = heldPromoBooking($this, $scenario);

    $reservation = Reservation::query()->where('uuid', $reservationUuid)->firstOrFail();
    ReservationPassenger::query()->create([
        'reservation_id' => $reservation->id,
        'passenger_index' => 0,
        'first_name' => 'Aïcha',
        'last_name' => 'HOUNKPÈ',
        'phone' => '+22901000000',
        'status' => 'active',
    ]);

    $payload = app(FiscalInvoicePayloadBuilder::class)->build($reservation);

    expect($payload['items'][0]['price'])->toBe(10000)
        ->and($payload['items'][0])->not->toHaveKey('originalPrice')
        ->and($payload['items'][0])->not->toHaveKey('priceModification');
});

/** @param array<string, mixed> $overrides */
function bookingPromotion(array $overrides = []): Promotion
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

/**
 * Parcours mobile complet jusqu'au hold : devis puis blocage de siège.
 * Retourne l'UUID de la réservation détenue.
 */
function heldPromoBooking(TestCase $test, array $scenario, ?string $promoCode = null): string
{
    Sanctum::actingAs($scenario['user']);

    $quotePayload = [
        'passenger_count' => 1,
        'legs' => [[
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ];

    if ($promoCode !== null) {
        $quotePayload['promo_code'] = $promoCode;
    }

    $quoteUuid = $test->postJson('/api/v1/bus/itinerary-quotes', $quotePayload)
        ->assertOk()
        ->json('data.itinerary_quote_uuid');

    return $test->postJson('/api/v1/bus/itinerary-holds', [
        'itinerary_quote_uuid' => $quoteUuid,
        'idempotency_key' => 'promo-hold-'.fake()->uuid(),
        'passengers' => [[
            'passenger_index' => 0,
            'seat_assignments' => [
                ['leg_order' => 1, 'seat_uuid' => $scenario['seat']->uuid],
            ],
        ]],
    ])->assertCreated()->json('data.uuid');
}

/** @return array<string, mixed> */
function promoBookingScenario(): array
{
    $departureDate = now()->addDays(7)->toDateString();
    $user = User::factory()->create();
    $originPhysical = PhysicalStop::create(['name' => 'Promo Booking Origin', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $destinationPhysical = PhysicalStop::create(['name' => 'Promo Booking Destination', 'city' => 'Parakou', 'country' => 'BJ', 'status' => 'active']);

    $line = Line::create(['code' => 'PB-L1', 'name' => 'Cotonou-Parakou', 'countries_crossed' => ['BJ'], 'status' => 'active']);

    $origin = Stop::create(['line_id' => $line->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Origin', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
    $destination = Stop::create(['line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Destination', 'city' => 'Parakou', 'country' => 'BJ', 'stop_order' => 2, 'status' => 'active']);

    $bus = Bus::create(['registration_number' => 'PB-BUS-1', 'brand' => 'KORA', 'model' => 'Test', 'seat_count' => 1, 'status' => 'active']);
    $seat = Seat::create(['bus_id' => $bus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    $departure = Departure::create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => $departureDate,
        'departure_time' => '08:00',
        'scheduled_at' => CarbonImmutable::parse($departureDate.' 08:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $origin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $destination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 300, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse($departureDate.' 13:00', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    Fare::create(['line_id' => $line->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id, 'base_amount' => 10000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);

    return compact('user', 'line', 'origin', 'destination', 'bus', 'seat', 'departure', 'departureDate');
}

it('lets the counter agent apply a promo code on an active hold via the web route', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $scenario = promoBookingScenario();
    $scenario['user']->assignRole('agency_agent');
    bookingPromotion(['code' => 'TEST10', 'benefit_value' => 10]);
    $reservationUuid = heldPromoBooking($this, $scenario);

    $this->actingAs($scenario['user'])
        ->put("/sale/{$reservationUuid}/promo-code", [
            'promo_code' => 'TEST10',
        ])
        ->assertRedirect();

    $reservation = Reservation::query()->where('uuid', $reservationUuid)->firstOrFail();
    expect($reservation->promotion_discount_amount)->toBe(1000)
        ->and($reservation->total_amount)->toBe(9000)
        ->and($reservation->total_snapshot['promotion']['code'])->toBe('TEST10');
});

it('lets the counter agent remove the promo code via the web route', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $scenario = promoBookingScenario();
    $scenario['user']->assignRole('agency_agent');
    bookingPromotion(['code' => 'TEST10', 'benefit_value' => 10]);
    $reservationUuid = heldPromoBooking($this, $scenario);

    $this->actingAs($scenario['user'])
        ->put("/sale/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])
        ->assertRedirect();
    $this->actingAs($scenario['user'])
        ->put("/sale/{$reservationUuid}/promo-code", ['promo_code' => null])
        ->assertRedirect();

    $reservation = Reservation::query()->where('uuid', $reservationUuid)->firstOrFail();
    expect($reservation->promotion_discount_amount)->toBe(0)
        ->and($reservation->total_amount)->toBe(10000)
        ->and($reservation->promotion_id)->toBeNull();
});

it('rejects an unknown promo code via the web route without touching the total', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $scenario = promoBookingScenario();
    $scenario['user']->assignRole('agency_agent');
    $reservationUuid = heldPromoBooking($this, $scenario);

    $response = $this->actingAs($scenario['user'])
        ->put("/sale/{$reservationUuid}/promo-code", ['promo_code' => 'INCONNU']);

    $response->assertSessionHasErrors('promo_code');
    expect(Reservation::query()->where('uuid', $reservationUuid)->firstOrFail()->total_amount)->toBe(10000);
});

it('forbids the web promo route to a user without counter-sale rights', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $scenario = promoBookingScenario();
    bookingPromotion(['code' => 'TEST10', 'benefit_value' => 10]);
    $reservationUuid = heldPromoBooking($this, $scenario);

    $this->actingAs(User::factory()->create())
        ->put("/sale/{$reservationUuid}/promo-code", ['promo_code' => 'TEST10'])
        ->assertForbidden();
});
