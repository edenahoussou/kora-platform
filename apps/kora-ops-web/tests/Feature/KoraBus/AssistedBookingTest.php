<?php

use App\Models\User;
use App\Modules\AssistedBooking\Data\AssistedBookingStatus;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->travelTo('2026-09-18 08:00:00');
    $this->seed(KoraBusRolePermissionSeeder::class);
});

afterEach(function (): void {
    $this->travelBack();
});

it('lists only active agencies with an assistance contact', function (): void {
    $scenario = assistedBookingScenario();
    Agency::factory()->create(['status' => 'suspended', 'call_phone' => '+2290100000002']);
    Agency::factory()->create(['call_phone' => null, 'whatsapp_phone' => null]);

    $this->actingAs($scenario['passenger'])
        ->getJson('/api/v1/assisted-bookings/agencies')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $scenario['agency']->uuid)
        ->assertJsonPath('data.0.call_phone', '+2290100000000')
        ->assertJsonPath('data.0.whatsapp_phone', '+2290100000001')
        ->assertJsonMissingPath('data.0.id');
});

it('moves the original reservation to assistance without occupying its requested seat', function (): void {
    $scenario = assistedBookingScenario();

    $response = $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
            'agency_uuid' => $scenario['agency']->uuid,
            'channel' => 'whatsapp',
            'contact_name' => 'Afi',
            'contact_phone' => '+2290197000000',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.reservation.reference', fn (string $reference): bool => str_starts_with($reference, 'KBR-'))
        ->assertJsonPath('data.agency.uuid', $scenario['agency']->uuid)
        ->assertJsonPath('data.contact.name', 'Afi')
        ->assertJsonPath('data.channels.whatsapp_phone', '+2290100000001')
        ->assertJsonMissingPath('data.reservation.id');

    $assistedBooking = AssistedBooking::query()->firstOrFail();

    expect($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::AssistancePending)
        ->and($scenario['segment']->fresh()->status)->toBe(ReservationSegmentStatus::Requested)
        ->and($scenario['segment']->fresh()->released_at)->not->toBeNull()
        ->and($assistedBooking->status)->toBe(AssistedBookingStatus::Pending)
        ->and($assistedBooking->snapshot['requested_seats'][0]['code'])->toBe('2A');

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => $assistedBooking->getMorphClass(),
        'subject_id' => $assistedBooking->id,
        'event' => 'requested',
    ]);

    $response->assertJsonPath('data.whatsapp_message', fn (string $message): bool => str_contains($message, $scenario['reservation']->fresh()->reference));
});

it('replays the same assistance request without creating a duplicate', function (): void {
    $scenario = assistedBookingScenario();
    $payload = [
        'agency_uuid' => $scenario['agency']->uuid,
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ];

    $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", $payload)
        ->assertCreated();
    $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", $payload)
        ->assertOk();

    $this->assertDatabaseCount('assisted_bookings', 1);
});

it('returns 403 when another passenger requests assistance for the reservation', function (): void {
    $scenario = assistedBookingScenario();
    $otherPassenger = User::factory()->create();
    $otherPassenger->assignRole('passenger');

    $this->actingAs($otherPassenger)
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
            'agency_uuid' => $scenario['agency']->uuid,
            'channel' => 'whatsapp',
            'contact_name' => 'Intrus',
            'contact_phone' => '+2290197999999',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('assisted_bookings', 0);
});

it('resumes online on the same reservation with a fresh quote and atomic seat hold', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'agency_uuid' => $scenario['agency']->uuid,
        'channel' => 'whatsapp',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();

    $resumeKey = (string) Str::uuid();
    $payload = [
        'idempotency_key' => $resumeKey,
        'passengers' => [[
            'passenger_index' => 0,
            'seat_assignments' => [['leg_order' => 1, 'seat_uuid' => $scenario['seats']['2A']->uuid]],
        ]],
    ];

    $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance/resume-online", $payload)
        ->assertOk()
        ->assertJsonPath('data.uuid', $scenario['reservation']->uuid)
        ->assertJsonPath('data.status', 'held')
        ->assertJsonPath('data.seats.0.seat_code', '2A');
    $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance/resume-online", $payload)
        ->assertOk();

    expect($scenario['reservation']->fresh()->uuid)->toBe($scenario['reservation']->uuid)
        ->and($scenario['reservation']->fresh()->segments()->count())->toBe(1)
        ->and(AssistedBooking::query()->firstOrFail()->status)->toBe(AssistedBookingStatus::Cancelled);
});

it('lets exactly one eligible agency agent claim the dossier without holding a seat', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'agency_uuid' => $scenario['agency']->uuid,
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $firstAgent = agencyAgent($scenario['agency']);
    $secondAgent = agencyAgent($scenario['agency']);

    $this->actingAs($firstAgent)
        ->post("/assisted-bookings/{$assistance->uuid}/claim")
        ->assertRedirect();
    $this->actingAs($secondAgent)
        ->post("/assisted-bookings/{$assistance->uuid}/claim")
        ->assertForbidden();

    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::InProgress)
        ->and($assistance->fresh()->assigned_user_id)->toBe($firstAgent->id)
        ->and($scenario['reservation']->fresh()->segments()->where('status', ReservationSegmentStatus::Held->value)->count())->toBe(0)
        ->and($assistance->fresh()->current_total_amount)->toBe(8500);
});

it('prevents another agency and the finance director from reading an assisted dossier', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'agency_uuid' => $scenario['agency']->uuid,
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $otherAgency = Agency::factory()->create(['call_phone' => '+2290100000099']);
    $otherManager = agencyAgent($otherAgency, 'agency_manager');
    $financeDirector = User::factory()->create();
    $financeDirector->assignRole('daf');

    $this->actingAs($otherManager)->get("/assisted-bookings/{$assistance->uuid}")->assertForbidden();
    $this->actingAs($financeDirector)->get("/assisted-bookings/{$assistance->uuid}")->assertForbidden();
});

it('requires a manager refusal reason and closes the reservation atomically', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'agency_uuid' => $scenario['agency']->uuid,
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $manager = agencyAgent($scenario['agency'], 'agency_manager');

    $this->actingAs($manager)
        ->post("/assisted-bookings/{$assistance->uuid}/refuse", ['reason_code' => 'other'])
        ->assertSessionHasErrors('reason_note');
    $this->actingAs($manager)
        ->post("/assisted-bookings/{$assistance->uuid}/refuse", [
            'reason_code' => 'payment_not_received',
            'reason_note' => 'Le dépôt annoncé n’a pas été reçu.',
        ])->assertRedirect();

    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::Refused)
        ->and($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($scenario['segment']->fresh()->status)->toBe(ReservationSegmentStatus::Cancelled);
});

it('finalizes assistance in the same transaction as the counter payment and fiscal invoice', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'agency_uuid' => $scenario['agency']->uuid,
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $agent = agencyAgent($scenario['agency']);
    $this->actingAs($agent)->post("/assisted-bookings/{$assistance->uuid}/claim")->assertRedirect();
    $this->actingAs($agent)->post("/assisted-bookings/{$assistance->uuid}/prepare-sale", [
        'passengers' => [[
            'passenger_index' => 0,
            'seat_assignments' => [['leg_order' => 1, 'seat_uuid' => $scenario['seats']['2A']->uuid]],
        ]],
    ])->assertRedirect();

    $cashSession = CashSession::query()->create([
        'agent_id' => $agent->id,
        'agency_id' => $scenario['agency']->id,
        'business_date' => now()->toDateString(),
        'opening_float' => 0,
        'expected_balance' => 0,
        'total_cash' => 0,
        'total_mobile_money' => 0,
        'status' => 'open',
        'opened_at' => now(),
    ]);
    CashLedger::query()->create([
        'cash_session_id' => $cashSession->id,
        'ledger_key' => 'cash',
        'kind' => 'cash',
        'opening_balance' => 0,
        'expected_balance' => 0,
        'status' => 'open',
        'opened_by' => $agent->id,
        'opened_at' => now(),
    ]);
    $paymentKey = (string) Str::uuid();
    $payload = [
        'method' => 'cash',
        'amount' => 8500,
        'cash_received_amount' => 8500,
        'payer_name' => 'Afi Sossou',
        'idempotency_key' => $paymentKey,
        'verified_manually' => true,
    ];

    $this->actingAs($agent)->post(route('sale.payment', $scenario['reservation']), $payload)->assertRedirect();
    $this->actingAs($agent)->post(route('sale.payment', $scenario['reservation']), $payload)->assertRedirect();

    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::Finalized)
        ->and($scenario['reservation']->fresh()->selling_agency_id)->toBe($scenario['agency']->id)
        ->and($scenario['reservation']->fresh()->payments()->count())->toBe(1)
        ->and($scenario['reservation']->fresh()->fiscalInvoice()->count())->toBe(1);
});

it('expires assistance at the effective commercial cutoff', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'agency_uuid' => $scenario['agency']->uuid,
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $this->travelTo('2026-09-20 11:31:00');

    $this->artisan('bus:expire-assisted-bookings')->assertSuccessful();

    expect(AssistedBooking::query()->firstOrFail()->status)->toBe(AssistedBookingStatus::Expired)
        ->and($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Expired)
        ->and($scenario['segment']->fresh()->status)->toBe(ReservationSegmentStatus::Expired);
});

it('marks the same reservation unavailable when its departure is cancelled', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'agency_uuid' => $scenario['agency']->uuid,
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $scenario['departure']->update(['status' => 'cancelled']);
    $agent = agencyAgent($scenario['agency']);

    $this->actingAs($agent)
        ->post("/assisted-bookings/{$assistance->uuid}/claim")
        ->assertConflict();

    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::Unavailable)
        ->and($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Unavailable);
});

/**
 * @return array{
 *     passenger: User,
 *     agency: Agency,
 *     reservation: Reservation,
 *     segment: ReservationSegment,
 *     departure: Departure,
 *     origin: Stop,
 *     destination: Stop,
 *     seats: array<string, Seat>
 * }
 */
function assistedBookingScenario(): array
{
    $passenger = User::factory()->create(['name' => 'Afi Sossou', 'phone' => '+2290197000000']);
    $passenger->assignRole('passenger');

    $agency = Agency::factory()->create([
        'name' => 'Agence Ganhi',
        'city' => 'Cotonou',
        'status' => 'active',
        'call_phone' => '+2290100000000',
        'whatsapp_phone' => '+2290100000001',
    ]);
    $line = Line::query()->create([
        'code' => 'AB-L1', 'name' => 'Cotonou - Parakou', 'countries_crossed' => ['BJ'],
        'distance_km' => 420, 'estimated_duration_minutes' => 480, 'status' => 'active',
    ]);
    $originPhysical = PhysicalStop::query()->create([
        'name' => 'Ganhi Assistance', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active',
    ]);
    $destinationPhysical = PhysicalStop::query()->create([
        'name' => 'Parakou Assistance', 'city' => 'Parakou', 'country' => 'BJ', 'status' => 'active',
    ]);
    $origin = Stop::query()->create([
        'line_id' => $line->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Ganhi',
        'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Parakou',
        'city' => 'Parakou', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 420, 'status' => 'active',
    ]);
    $bus = Bus::query()->create([
        'registration_number' => 'AB-BUS-001', 'brand' => 'KORA', 'model' => 'Coach',
        'seat_count' => 2, 'status' => 'active',
    ]);
    $seats = [
        '2A' => Seat::query()->create([
            'bus_id' => $bus->id, 'code' => '2A', 'type' => 'standard', 'sellable' => true, 'status' => 'active',
        ]),
        '2B' => Seat::query()->create([
            'bus_id' => $bus->id, 'code' => '2B', 'type' => 'standard', 'sellable' => true, 'status' => 'active',
        ]),
    ];
    $departure = Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => '2026-09-20',
        'departure_time' => '12:00', 'estimated_arrival_at' => '2026-09-20 20:00:00', 'status' => 'scheduled',
    ]);
    Fare::query()->create([
        'line_id' => $line->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id,
        'base_amount' => 8500, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active',
    ]);

    $reservation = Reservation::query()->create([
        'created_by_user_id' => $passenger->id,
        'payer_user_id' => $passenger->id,
        'payer_type' => 'personal',
        'purchase_type' => 'individual',
        'group_size' => 1,
        'status' => ReservationStatus::Held,
        'total_amount' => 8500,
        'currency' => 'XOF',
        'channel' => ReservationChannel::Mobile,
        'expires_at' => now()->addMinutes(10),
    ]);
    $traveller = ReservationPassenger::query()->create([
        'reservation_id' => $reservation->id, 'user_id' => $passenger->id, 'passenger_index' => 0,
        'first_name' => 'Afi', 'last_name' => 'Sossou', 'phone' => '+2290197000000', 'status' => 'active',
    ]);
    $segment = ReservationSegment::query()->create([
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $traveller->id,
        'passenger_index' => 0,
        'leg_order' => 1,
        'direction' => 'outbound',
        'departure_id' => $departure->id,
        'seat_id' => $seats['2A']->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'origin_stop_order' => 1,
        'destination_stop_order' => 2,
        'status' => ReservationSegmentStatus::Held,
    ]);

    return compact('passenger', 'agency', 'reservation', 'segment', 'departure', 'origin', 'destination', 'seats');
}

function agencyAgent(Agency $agency, string $role = 'agency_agent'): User
{
    $agent = User::factory()->create();
    $agent->forceFill(['agency_id' => $agency->id])->save();
    $agent->assignRole($role);

    return $agent;
}
