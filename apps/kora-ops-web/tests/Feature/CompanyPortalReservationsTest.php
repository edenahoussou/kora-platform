<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\ItineraryQuoteLeg;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use App\Modules\CompanyAccounts\Models\CompanyCreditHold;
use App\Modules\CompanyAccounts\Models\CompanyCreditTransaction;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function portalHistoryAccount(array $attributes = []): CompanyAccount
{
    return CompanyAccount::query()->create([
        'name' => 'Société Horizon',
        'type' => 'company',
        'reference' => 'HIST-'.fake()->unique()->numerify('#####'),
        'monthly_credit_limit' => 500000,
        'status' => 'active',
        ...$attributes,
    ]);
}

/** @return array{0: User, 1: CompanyAccount} */
function portalHistoryReferent(array $accountAttributes = [], string $role = 'referent'): array
{
    $user = User::factory()->create();
    $account = portalHistoryAccount($accountAttributes);

    CompanyAccountMember::query()->create([
        'company_account_id' => $account->id,
        'user_id' => $user->id,
        'role' => $role,
        'status' => 'active',
    ]);

    return [$user, $account];
}

/** @return array{reservation: Reservation, passenger: ReservationPassenger, ticket: Ticket} */
function portalHistoryReservation(CompanyAccount $account, array $attributes = []): array
{
    $departureDate = now()->addDays(10)->toDateString();
    $physicalOrigin = PhysicalStop::create(['name' => 'Gare Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $physicalDestination = PhysicalStop::create(['name' => 'Gare Lomé', 'city' => 'Lomé', 'country' => 'TG', 'status' => 'active']);
    $line = Line::create(['code' => 'PH-'.fake()->unique()->numerify('###'), 'name' => 'Cotonou-Lomé', 'countries_crossed' => ['BJ'], 'status' => 'active']);
    $origin = Stop::create(['line_id' => $line->id, 'physical_stop_id' => $physicalOrigin->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
    $destination = Stop::create(['line_id' => $line->id, 'physical_stop_id' => $physicalDestination->id, 'name' => 'Lomé', 'city' => 'Lomé', 'country' => 'TG', 'stop_order' => 2, 'status' => 'active']);
    $bus = Bus::create(['registration_number' => 'PH-'.fake()->unique()->numerify('#####'), 'brand' => 'KORA', 'model' => 'Test', 'seat_count' => 1, 'status' => 'active']);
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
            ['stop_uuid' => $destination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 180, 'timezone' => 'Africa/Lome'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse($departureDate.' 11:00', 'Africa/Lome'),
        'status' => 'scheduled',
    ]);

    $quote = ItineraryQuote::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => null,
        'passenger_count' => 2,
        'subtotal_amount' => 20000,
        'dynamic_adjustment_amount' => 0,
        'group_discount_amount' => 0,
        'promotion_discount_amount' => 0,
        'total_amount' => 19000,
        'currency' => 'XOF',
        'snapshot' => [
            'group_discount' => ['applied' => false, 'amount' => 0],
            'company_discount' => ['applied' => true, 'type' => 'percentage', 'value' => 5, 'amount' => 1000],
            'company_account_uuid' => $account->uuid,
        ],
        'expires_at' => now()->addHour(),
    ]);
    $priceQuote = PriceQuote::query()->create([
        'uuid' => (string) Str::uuid(),
        'departure_id' => $departure->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 10000,
        'coefficient' => 1.0,
        'final_amount' => 10000,
        'passenger_count' => 2,
        'subtotal_amount' => 20000,
        'group_discount_amount' => 0,
        'total_amount' => 20000,
        'currency' => 'XOF',
        'snapshot' => [],
        'expires_at' => now()->addHour(),
    ]);
    ItineraryQuoteLeg::query()->create([
        'itinerary_quote_id' => $quote->id,
        'direction' => 'outbound',
        'leg_order' => 1,
        'price_quote_id' => $priceQuote->id,
        'departure_id' => $departure->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'amount' => 10000,
    ]);

    $reservation = Reservation::query()->create([
        'company_account_id' => $account->id,
        'payer_type' => 'company',
        'purchase_type' => 'group',
        'group_size' => 2,
        'channel' => ReservationChannel::CompanyPortal,
        'itinerary_quote_id' => $quote->id,
        'status' => 'ticketed',
        'total_amount' => 19000,
        'currency' => 'XOF',
        'idempotency_key' => (string) Str::uuid(),
        'expires_at' => now()->addHour(),
        'total_snapshot' => $quote->snapshot,
        ...$attributes,
    ]);

    $passenger = ReservationPassenger::query()->create([
        'reservation_id' => $reservation->id,
        'status' => 'active',
        'passenger_index' => 0,
        'first_name' => 'Awa',
        'last_name' => 'Sissoko',
        'phone' => '+22990000001',
        'document_type' => 'passport',
        'document_number' => 'A123456',
    ]);
    ReservationSegment::query()->create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'leg_order' => 1,
        'direction' => 'outbound',
        'passenger_index' => 0,
        'reservation_passenger_id' => $passenger->id,
        'departure_id' => $departure->id,
        'seat_id' => $seat->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'origin_stop_order' => 1,
        'destination_stop_order' => 2,
        'status' => 'ticketed',
    ]);
    $ticket = Ticket::query()->create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
        'qr_code' => 'qr-'.(string) Str::uuid(),
        'short_code' => 'KBR-'.fake()->unique()->numerify('######'),
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    CompanyCreditHold::query()->create([
        'uuid' => (string) Str::uuid(),
        'company_account_id' => $account->id,
        'reservation_id' => $reservation->id,
        'amount' => 19000,
        'period_key' => now()->format('Y-m'),
        'status' => 'converted',
        'expires_at' => now()->addHour(),
        'converted_at' => now(),
    ]);
    CompanyCreditTransaction::query()->create([
        'uuid' => (string) Str::uuid(),
        'company_account_id' => $account->id,
        'reservation_id' => $reservation->id,
        'type' => 'debit',
        'amount' => 19000,
        'period_key' => now()->format('Y-m'),
        'reference' => $reservation->reference,
    ]);

    return ['reservation' => $reservation, 'passenger' => $passenger, 'ticket' => $ticket];
}

it('redirects guests and denies non-referents from the purchase history', function (): void {
    $this->get(route('company-portal.reservations.index'))
        ->assertRedirect(route('login'));

    [$member] = portalHistoryReferent([], 'member');

    $this->actingAs($member)
        ->get(route('company-portal.reservations.index'))
        ->assertForbidden();
});

it('lists only the referent own account reservations', function (): void {
    [$referent, $account] = portalHistoryReferent();
    [, $otherAccount] = portalHistoryReferent();

    $own = portalHistoryReservation($account)['reservation'];
    $other = portalHistoryReservation($otherAccount)['reservation'];

    $this->actingAs($referent)
        ->get(route('company-portal.reservations.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('company-portal/reservations')
            ->where('account.uuid', $account->uuid)
            ->has('reservations.data', 1)
            ->where('reservations.data.0.uuid', $own->uuid)
            ->where('reservations.data.0.reference', $own->reference)
            ->where('reservations.data.0.totalAmount', 19000)
            ->missing('reservations.data.0.id')
        );

    expect(Reservation::query()->find($other->id))->not->toBeNull();
});

it('filters the history by status, month and search', function (): void {
    [$referent, $account] = portalHistoryReferent();

    $ticketed = portalHistoryReservation($account)['reservation'];
    portalHistoryReservation($account, ['status' => 'cancelled']);

    $this->actingAs($referent)
        ->get(route('company-portal.reservations.index', ['status' => 'cancelled']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reservations.data', 1)
            ->where('reservations.data.0.status', 'cancelled')
        );

    $this->actingAs($referent)
        ->get(route('company-portal.reservations.index', ['month' => '2020-01']))
        ->assertInertia(fn (Assert $page): Assert => $page->has('reservations.data', 0));

    $this->actingAs($referent)
        ->get(route('company-portal.reservations.index', ['month' => now()->format('Y-m')]))
        ->assertInertia(fn (Assert $page): Assert => $page->has('reservations.data', 2));

    $this->actingAs($referent)
        ->get(route('company-portal.reservations.index', ['q' => $ticketed->reference]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('reservations.data', 1)
            ->where('reservations.data.0.uuid', $ticketed->uuid)
        );

    $this->actingAs($referent)
        ->get(route('company-portal.reservations.index', ['q' => 'Sissoko']))
        ->assertInertia(fn (Assert $page): Assert => $page->has('reservations.data', 2));
});

it('shows the reservation detail with travelers, tickets and credit imputation', function (): void {
    [$referent, $account] = portalHistoryReferent();
    ['reservation' => $reservation, 'ticket' => $ticket] = portalHistoryReservation($account);

    $this->actingAs($referent)
        ->get(route('company-portal.reservations.show', ['reservation' => $reservation->uuid]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('company-portal/reservation-detail')
            ->where('reservation.reference', $reservation->reference)
            ->where('reservation.pricing.companyDiscount.applied', true)
            ->has('reservation.legs', 1)
            ->has('reservation.passengers', 1)
            ->where('reservation.passengers.0.firstName', 'Awa')
            ->where('reservation.passengers.0.tickets.0.shortCode', $ticket->short_code)
            ->has('reservation.passengers.0.seats', 1)
            ->has('reservation.credit.holds', 1)
            ->has('reservation.credit.transactions', 1)
        );
});

it('hides another company account reservation detail', function (): void {
    [$referent] = portalHistoryReferent();
    [, $otherAccount] = portalHistoryReferent();
    $other = portalHistoryReservation($otherAccount)['reservation'];

    $this->actingAs($referent)
        ->get(route('company-portal.reservations.show', ['reservation' => $other->uuid]))
        ->assertNotFound();
});

it('exports the filtered reservations as csv without internal identifiers', function (): void {
    [$referent, $account] = portalHistoryReferent();
    [, $otherAccount] = portalHistoryReferent();
    $reservation = portalHistoryReservation($account)['reservation'];
    $other = portalHistoryReservation($otherAccount)['reservation'];

    $response = $this->actingAs($referent)
        ->get(route('company-portal.reservations.export', ['status' => 'ticketed']))
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain($reservation->reference)
        ->toContain('Cotonou')
        ->not->toContain($other->reference);

    $empty = $this->actingAs($referent)
        ->get(route('company-portal.reservations.export', ['status' => 'cancelled']))
        ->assertSuccessful();

    expect($empty->streamedContent())->not->toContain($reservation->reference);
});
