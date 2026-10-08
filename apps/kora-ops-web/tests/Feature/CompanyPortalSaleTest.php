<?php

use App\Models\User;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\Reservation;
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
use App\Modules\DynamicPricing\Models\ServiceClass;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus as BusFacade;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function companySaleAccount(array $attributes = []): CompanyAccount
{
    return CompanyAccount::query()->create([
        'name' => 'Société Horizon',
        'type' => 'company',
        'reference' => 'SALE-'.fake()->unique()->numerify('#####'),
        'monthly_credit_limit' => 500000,
        'status' => 'active',
        ...$attributes,
    ]);
}

function companySaleReferent(string $role = 'referent'): array
{
    $user = User::factory()->create();
    $account = companySaleAccount();

    CompanyAccountMember::query()->create([
        'company_account_id' => $account->id,
        'user_id' => $user->id,
        'role' => $role,
        'status' => 'active',
    ]);

    return [$user, $account];
}

it('redirects guests away from company self-service purchase', function (): void {
    $this->get(route('company-portal.sale.index'))
        ->assertRedirect(route('login'));
});

it('denies non-referents and inactive company accounts from purchase', function (): void {
    [$member] = companySaleReferent('member');
    [$referent, $inactiveAccount] = companySaleReferent();
    $inactiveAccount->update(['status' => 'inactive']);

    $this->actingAs($member)
        ->get(route('company-portal.sale.index'))
        ->assertForbidden();

    $this->actingAs($referent)
        ->get(route('company-portal.sale.index'))
        ->assertForbidden();
});

it('shows the referent only active roster and monthly credit on the purchase page', function (): void {
    [$referent, $account] = companySaleReferent();
    $active = $account->travelers()->create([
        'first_name' => 'Aïcha',
        'last_name' => 'Mensah',
        'phone' => '+22997000050',
        'status' => 'active',
    ]);
    $account->travelers()->create([
        'first_name' => 'Brice',
        'last_name' => 'Soglo',
        'phone' => '+22997000051',
        'status' => 'inactive',
    ]);

    $this->actingAs($referent)
        ->get(route('company-portal.sale.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('company-portal/sale')
            ->where('account.uuid', $account->uuid)
            ->where('credit.available', 500000)
            ->where('credit.monthlyLimit', 500000)
            ->has('roster', 1)
            ->where('roster.0.uuid', $active->uuid)
            ->where('roster.0.firstName', 'Aïcha')
            ->missing('roster.0.id'),
        );
});

it('quotes with the negotiated discount, binds active roster travelers and debits only company credit', function (): void {
    Carbon::setTestNow('2026-10-04 07:00:00');
    GroupBookingSetting::current();
    [$referent, $account] = companySaleReferent();
    $account->update([
        'discount_type' => CompanyDiscountType::Percentage,
        'discount_value' => 10,
    ]);
    $traveler = $account->travelers()->create([
        'first_name' => 'Aïcha',
        'last_name' => 'Mensah',
        'phone' => '+22997000050',
        'document_type' => 'passport',
        'document_number' => 'BJ-5544',
        'status' => 'active',
    ]);
    $itinerary = companyPortalSaleItinerary();

    $context = [
        'date' => '2026-10-10',
        'from' => 'Cotonou',
        'to' => 'Porto-Novo',
        'outbound_legs' => [[
            'departure_uuid' => $itinerary['departure']->uuid,
            'origin_stop_uuid' => $itinerary['origin']->uuid,
            'destination_stop_uuid' => $itinerary['destination']->uuid,
        ]],
        'passenger_count' => 1,
    ];

    $this->actingAs($referent)
        ->post(route('company-portal.sale.quote'), $context)
        ->assertRedirect();

    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();
    expect($quote->snapshot['company_account_uuid'])->toBe($account->uuid)
        ->and($quote->snapshot['group_discount']['applied'])->toBeFalse()
        ->and($quote->snapshot['company_discount']['amount'])->toBe(1000)
        ->and($quote->total_amount)->toBe(9000);

    $this->actingAs($referent)
        ->post(route('company-portal.sale.hold'), [
            ...$context,
            'itinerary_quote_uuid' => $quote->uuid,
            'idempotency_key' => (string) Str::uuid(),
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [[
                    'leg_order' => 1,
                    'direction' => 'outbound',
                    'seat_uuid' => $itinerary['seat']->uuid,
                ]],
            ]],
        ])
        ->assertRedirect();

    $reservation = Reservation::query()->where('channel', 'company_portal')->firstOrFail();
    expect($reservation->payer_type->value)->toBe('company')
        ->and($reservation->company_account_id)->toBe($account->id)
        ->and($reservation->total_amount)->toBe(9000);
    $this->assertDatabaseHas('company_credit_holds', [
        'reservation_id' => $reservation->id,
        'company_account_id' => $account->id,
        'amount' => 9000,
        'status' => 'reserved',
    ]);

    $this->actingAs($referent)
        ->post(route('company-portal.sale.passengers', $reservation), [
            'passengers' => [[
                'traveler_uuid' => $traveler->uuid,
                'passenger_index' => 0,
                'first_name' => 'Usurpé',
                'last_name' => 'Identité',
                'phone' => '+22997000099',
                'document_type' => 'national_id',
                'document_number' => 'CLIENT-OVERRIDE',
            ]],
        ])
        ->assertRedirect();

    $passenger = $reservation->fresh()->passengers()->sole();
    expect($passenger->first_name)->toBe('Aïcha')
        ->and($passenger->last_name)->toBe('Mensah')
        ->and($passenger->phone)->toBe('+22997000050')
        ->and($passenger->document_type->value)->toBe('passport')
        ->and($passenger->document_number)->toBe('BJ-5544');

    BusFacade::fake();
    $paymentKey = (string) Str::uuid();
    $this->actingAs($referent)
        ->post(route('company-portal.sale.payment', $reservation), [
            'idempotency_key' => $paymentKey,
            'method' => 'cash',
            'amount' => 1,
        ])
        ->assertRedirect();
    $this->actingAs($referent)
        ->post(route('company-portal.sale.payment', $reservation), [
            'idempotency_key' => $paymentKey,
            'method' => 'company_credit',
            'amount' => 9000,
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('company_credit_transactions', 1);
    $this->assertDatabaseHas('payments', [
        'reservation_id' => $reservation->id,
        'method' => 'company_credit',
        'amount' => 9000,
    ]);
    $this->assertDatabaseHas('company_credit_transactions', [
        'reservation_id' => $reservation->id,
        'company_account_id' => $account->id,
        'type' => 'debit',
        'amount' => 9000,
    ]);
    BusFacade::assertDispatchedSync(IssueReservationTicketsJob::class);
    expect($reservation->fresh()->status->value)->toBe('paid');
});

it('rolls back the seat hold when company credit is insufficient', function (): void {
    Carbon::setTestNow('2026-10-04 07:00:00');
    GroupBookingSetting::current();
    [$referent, $account] = companySaleReferent();
    $account->update([
        'monthly_credit_limit' => 1000,
        'discount_type' => CompanyDiscountType::Percentage,
        'discount_value' => 10,
    ]);
    $itinerary = companyPortalSaleItinerary();
    $context = [
        'date' => '2026-10-10',
        'from' => 'Cotonou',
        'to' => 'Porto-Novo',
        'outbound_legs' => [[
            'departure_uuid' => $itinerary['departure']->uuid,
            'origin_stop_uuid' => $itinerary['origin']->uuid,
            'destination_stop_uuid' => $itinerary['destination']->uuid,
        ]],
        'passenger_count' => 1,
    ];

    $this->actingAs($referent)->post(route('company-portal.sale.quote'), $context);
    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();

    $this->actingAs($referent)
        ->post(route('company-portal.sale.hold'), [
            ...$context,
            'itinerary_quote_uuid' => $quote->uuid,
            'idempotency_key' => (string) Str::uuid(),
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [[
                    'leg_order' => 1,
                    'direction' => 'outbound',
                    'seat_uuid' => $itinerary['seat']->uuid,
                ]],
            ]],
        ])
        ->assertSessionHasErrors('payer.company_account_uuid');

    expect(Reservation::query()->where('channel', 'company_portal')->count())->toBe(0)
        ->and($account->creditHolds()->count())->toBe(0);
    $this->assertDatabaseCount('reservation_segments', 0);
});

it('hides another company account reservations on every reservation action', function (): void {
    [$referent, $referentAccount] = companySaleReferent();
    [, $otherAccount] = companySaleReferent();
    $foreignTraveler = $otherAccount->travelers()->create([
        'first_name' => 'Aïcha',
        'last_name' => 'Mensah',
        'phone' => '+22997000050',
        'status' => 'active',
    ]);
    $reservation = Reservation::query()->create([
        'created_by_user_id' => $referent->id,
        'company_account_id' => $otherAccount->id,
        'payer_type' => 'company',
        'purchase_type' => 'individual',
        'group_size' => 1,
        'status' => 'held',
        'total_amount' => 10000,
        'currency' => 'XOF',
        'channel' => 'company_portal',
        'idempotency_key' => (string) Str::uuid(),
        'expires_at' => now()->addMinutes(15),
        'total_snapshot' => ['passenger_allocations' => [10000]],
    ]);

    $this->actingAs($referent)
        ->get(route('company-portal.sale.index', ['reservation_uuid' => $reservation->uuid]))
        ->assertNotFound();
    $this->actingAs($referent)
        ->post(route('company-portal.sale.release', $reservation))
        ->assertNotFound();
    $this->actingAs($referent)
        ->post(route('company-portal.sale.passengers', $reservation), [
            'passengers' => [[
                'traveler_uuid' => $foreignTraveler->uuid,
                'passenger_index' => 0,
            ]],
        ])
        ->assertNotFound();
    $this->actingAs($referent)
        ->post(route('company-portal.sale.payment', $reservation), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertNotFound();
    $this->actingAs($referent)
        ->post(route('company-portal.sale.tickets.retry', $reservation))
        ->assertNotFound();

    expect($referentAccount->reservations()->count())->toBe(0);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array{departure: Departure, origin: Stop, destination: Stop, seat: Seat} */
function companyPortalSaleItinerary(): array
{
    $line = Line::query()->create([
        'code' => 'CPS-01',
        'name' => 'Cotonou - Porto-Novo',
        'countries_crossed' => ['BJ'],
        'distance_km' => 40,
        'estimated_duration_minutes' => 60,
        'status' => 'active',
    ]);
    $originPhysicalStop = PhysicalStop::query()->create([
        'name' => 'Gare Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $origin = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $originPhysicalStop->id,
        'name' => 'Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $destinationPhysicalStop = PhysicalStop::query()->create([
        'name' => 'Gare Porto-Novo',
        'city' => 'Porto-Novo',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $destinationPhysicalStop->id,
        'name' => 'Porto-Novo',
        'city' => 'Porto-Novo',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 40,
        'status' => 'active',
    ]);
    $bus = Bus::query()->create([
        'registration_number' => 'CPS-BUS',
        'brand' => 'KORA',
        'model' => 'City',
        'seat_count' => 1,
        'status' => 'active',
    ]);
    $seat = Seat::query()->create([
        'bus_id' => $bus->id,
        'code' => '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-10-10',
        'departure_time' => '08:00',
        'estimated_arrival_at' => Carbon::parse('2026-10-10 09:00'),
        'status' => 'scheduled',
    ]);
    $serviceClass = ServiceClass::query()->create([
        'code' => 'cps-standard',
        'name' => 'Standard',
    ]);
    Fare::query()->create([
        'line_id' => $line->id,
        'service_class_id' => $serviceClass->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 10000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    return compact('departure', 'origin', 'destination', 'seat');
}
