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
use App\Modules\CompanyAccounts\Data\CompanyCreditHoldStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyCreditHold;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Settings\Settings\GeneralSettings;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->travelTo('2026-09-18 08:00:00');
    $this->seed(KoraBusRolePermissionSeeder::class);

    // Les canaux de support se lisent dans GeneralSettings (les clés
    // config('kora.assisted_bookings.*') ne servent qu'au seed initial).
    $settings = app(GeneralSettings::class);
    $settings->supportCallPhone = '+2290100000000';
    $settings->supportWhatsappPhone = '+2290100000001';
    $settings->save();
});

afterEach(function (): void {
    $this->travelBack();
});

it('does not expose an agency selection endpoint', function (): void {
    $scenario = assistedBookingScenario();

    $this->actingAs($scenario['passenger'])
        ->getJson('/api/v1/assisted-bookings/agencies')
        ->assertNotFound();
});

it('returns a stable 503 when the requested central support channel is unavailable', function (): void {
    $scenario = assistedBookingScenario();

    $settings = app(GeneralSettings::class);
    $settings->supportCallPhone = null;
    $settings->save();

    $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
            'channel' => 'call',
            'contact_name' => 'Afi',
            'contact_phone' => '+2290197000000',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'assisted_booking_support_unavailable')
        ->assertJsonPath('error.channel', 'call');

    $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
            'channel' => 'whatsapp',
            'contact_name' => 'Afi',
            'contact_phone' => '+2290197000000',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated();

    $this->assertDatabaseCount('assisted_bookings', 1);
});

it('moves the original reservation to assistance without occupying its requested seat', function (): void {
    $scenario = assistedBookingScenario();

    $response = $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
            'channel' => 'whatsapp',
            'contact_name' => 'Afi',
            'contact_phone' => '+2290197000000',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.reservation.reference', fn (string $reference): bool => str_starts_with($reference, 'KBR-'))
        ->assertJsonPath('data.handling_agency', null)
        ->assertJsonPath('data.contact.name', 'Afi')
        ->assertJsonPath('data.channels.whatsapp_phone', '+2290100000001')
        ->assertJsonMissingPath('data.reservation.id');

    $assistedBooking = AssistedBooking::query()->firstOrFail();

    expect($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::AssistancePending)
        ->and($scenario['segment']->fresh()->status)->toBe(ReservationSegmentStatus::Requested)
        ->and($scenario['segment']->fresh()->released_at)->not->toBeNull()
        ->and($assistedBooking->status)->toBe(AssistedBookingStatus::Pending)
        ->and($assistedBooking->preferred_agency_id)->toBeNull()
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
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $firstAgent = assistedBookingAgent($scenario['agency']);
    $secondAgent = assistedBookingAgent(Agency::factory()->create());

    $this->actingAs($firstAgent)
        ->post("/assisted-bookings/{$assistance->uuid}/claim")
        ->assertRedirect();
    $this->actingAs($secondAgent)
        ->post("/assisted-bookings/{$assistance->uuid}/claim")
        ->assertConflict();

    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::InProgress)
        ->and($assistance->fresh()->assigned_user_id)->toBe($firstAgent->id)
        ->and($scenario['reservation']->fresh()->segments()->where('status', ReservationSegmentStatus::Held->value)->count())->toBe(0)
        ->and($assistance->fresh()->current_total_amount)->toBe(8500);
});

it('exposes an unassigned dossier to every authorized agency but not to finance', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $otherAgency = Agency::factory()->create();
    $otherManager = assistedBookingAgent($otherAgency, 'agency_manager');
    $financeDirector = User::factory()->create();
    $financeDirector->assignRole('daf');

    $this->actingAs($otherManager)->get("/assisted-bookings/{$assistance->uuid}")->assertRedirect();
    $this->actingAs($financeDirector)->get("/assisted-bookings/{$assistance->uuid}")->assertForbidden();
});

it('requires a manager refusal reason and closes the reservation atomically', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $manager = assistedBookingAgent($scenario['agency'], 'agency_manager');
    $this->actingAs($manager)
        ->post("/assisted-bookings/{$assistance->uuid}/claim")
        ->assertRedirect();

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
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $agent = assistedBookingAgent($scenario['agency']);
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
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $scenario['departure']->update(['status' => 'cancelled']);
    $agent = assistedBookingAgent($scenario['agency']);

    $this->actingAs($agent)
        ->post("/assisted-bookings/{$assistance->uuid}/claim")
        ->assertConflict();

    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::Unavailable)
        ->and($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Unavailable);
});

it('arbitrates a concurrent claim against an online resume: the first wins, the other conflicts', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $agent = assistedBookingAgent($scenario['agency']);

    // Agent claims first → wins
    $this->actingAs($agent)
        ->post("/assisted-bookings/{$assistance->uuid}/claim")
        ->assertRedirect();

    // Passenger tries to resume online → conflict (assistance no longer Pending)
    $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance/resume-online", [
            'idempotency_key' => (string) Str::uuid(),
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [['leg_order' => 1, 'seat_uuid' => $scenario['seats']['2A']->uuid]],
            ]],
        ])
        ->assertConflict();

    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::InProgress)
        ->and($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::AssistancePending);
});

it('lets an agent release a dossier back to the queue, then another agent of the same agency can claim it', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $firstAgent = assistedBookingAgent($scenario['agency']);
    $secondAgent = assistedBookingAgent($scenario['agency']);

    $this->actingAs($firstAgent)->post("/assisted-bookings/{$assistance->uuid}/claim")->assertRedirect();
    $this->actingAs($firstAgent)
        ->post("/assisted-bookings/{$assistance->uuid}/release", ['reason' => 'Client injoignable.'])
        ->assertRedirect();

    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::Pending)
        ->and($assistance->fresh()->preferred_agency_id)->toBeNull()
        ->and($assistance->fresh()->assigned_user_id)->toBeNull();

    $this->actingAs($secondAgent)->post("/assisted-bookings/{$assistance->uuid}/claim")->assertRedirect();
    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::InProgress)
        ->and($assistance->fresh()->assigned_user_id)->toBe($secondAgent->id);
});

it('requires a reason when releasing a dossier', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $agent = assistedBookingAgent($scenario['agency']);
    $this->actingAs($agent)->post("/assisted-bookings/{$assistance->uuid}/claim")->assertRedirect();

    $this->actingAs($agent)
        ->post("/assisted-bookings/{$assistance->uuid}/release")
        ->assertSessionHasErrors('reason');
});

it('lets a network operator reassign a dossier to an agency queue', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $networkOperator = User::factory()->create();
    $networkOperator->assignRole('operations_director');
    $targetAgency = Agency::factory()->create();

    $this->actingAs($networkOperator)
        ->post("/assisted-bookings/{$assistance->uuid}/reassign", [
            'agency_uuid' => $targetAgency->uuid,
            'reason' => 'Réaffectation réseau.',
        ])
        ->assertRedirect();

    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::Pending)
        ->and($assistance->fresh()->preferred_agency_id)->toBe($targetAgency->id)
        ->and($assistance->fresh()->assigned_user_id)->toBeNull();

    $targetAgent = assistedBookingAgent($targetAgency);
    $this->actingAs($targetAgent)
        ->post("/assisted-bookings/{$assistance->uuid}/claim")
        ->assertRedirect();

    expect($assistance->fresh()->status)->toBe(AssistedBookingStatus::InProgress)
        ->and($assistance->fresh()->assigned_user_id)->toBe($targetAgent->id);
});

it('forbids an agency manager from reassigning to a different agency without network permission', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $manager = assistedBookingAgent($scenario['agency'], 'agency_manager');
    $otherAgency = Agency::factory()->create();

    $this->actingAs($manager)
        ->post("/assisted-bookings/{$assistance->uuid}/reassign", [
            'agency_uuid' => $otherAgency->uuid,
            'reason' => 'Tentative interdite.',
        ])
        ->assertForbidden();
});

it('reports a seat conflict when the requested seat is taken by another reservation on resume', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'channel' => 'whatsapp',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();

    // Occupy seat 2A with another reservation segment
    $otherPassenger = User::factory()->create();
    $otherPassenger->assignRole('passenger');
    $otherReservation = Reservation::query()->create([
        'created_by_user_id' => $otherPassenger->id,
        'payer_user_id' => $otherPassenger->id,
        'payer_type' => 'personal',
        'purchase_type' => 'individual',
        'group_size' => 1,
        'status' => ReservationStatus::Held,
        'total_amount' => 8500,
        'currency' => 'XOF',
        'channel' => ReservationChannel::Mobile,
        'expires_at' => now()->addMinutes(10),
    ]);
    $otherTraveller = ReservationPassenger::query()->create([
        'reservation_id' => $otherReservation->id, 'user_id' => $otherPassenger->id, 'passenger_index' => 0,
        'first_name' => 'Other', 'last_name' => 'Pass', 'status' => 'active',
    ]);
    ReservationSegment::query()->create([
        'reservation_id' => $otherReservation->id,
        'reservation_passenger_id' => $otherTraveller->id,
        'passenger_index' => 0,
        'leg_order' => 1,
        'direction' => 'outbound',
        'departure_id' => $scenario['departure']->id,
        'seat_id' => $scenario['seats']['2A']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'origin_stop_order' => 1,
        'destination_stop_order' => 2,
        'status' => ReservationSegmentStatus::Held,
    ]);

    $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance/resume-online", [
            'idempotency_key' => (string) Str::uuid(),
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [['leg_order' => 1, 'seat_uuid' => $scenario['seats']['2A']->uuid]],
            ]],
        ])
        ->assertConflict()
        ->assertJsonPath('seat_code', '2A');

    // The passenger can still resume with the other free seat
    $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance/resume-online", [
            'idempotency_key' => (string) Str::uuid(),
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [['leg_order' => 1, 'seat_uuid' => $scenario['seats']['2B']->uuid]],
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('data.seats.0.seat_code', '2B');
});

it('releases company credit on assistance request and re-reserves it on online resume', function (): void {
    $scenario = assistedBookingScenario();
    $account = CompanyAccount::query()->create([
        'name' => 'ACME Corp',
        'type' => 'company',
        'reference' => 'ACME-001',
        'monthly_credit_limit' => 500000,
        'status' => 'active',
    ]);
    $scenario['reservation']->update([
        'payer_type' => 'company',
        'company_account_id' => $account->id,
    ]);
    CompanyCreditHold::query()->create([
        'company_account_id' => $account->id,
        'reservation_id' => $scenario['reservation']->id,
        'amount' => 8500,
        'period_key' => now()->format('Y-m'),
        'status' => CompanyCreditHoldStatus::Reserved,
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'channel' => 'whatsapp',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();

    expect(CompanyCreditHold::query()->first()->status)->toBe(CompanyCreditHoldStatus::Released);

    $this->actingAs($scenario['passenger'])
        ->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance/resume-online", [
            'idempotency_key' => (string) Str::uuid(),
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [['leg_order' => 1, 'seat_uuid' => $scenario['seats']['2A']->uuid]],
            ]],
        ])
        ->assertOk();

    expect(CompanyCreditHold::query()->where('reservation_id', $scenario['reservation']->id)->where('status', CompanyCreditHoldStatus::Reserved->value)->exists())->toBeTrue();
});

it('keeps assistance finalized and does not duplicate tickets when the issuance job is replayed', function (): void {
    $scenario = assistedBookingScenario();
    $this->actingAs($scenario['passenger'])->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/assistance", [
        'channel' => 'call',
        'contact_name' => 'Afi',
        'contact_phone' => '+2290197000000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $assistance = AssistedBooking::query()->firstOrFail();
    $agent = assistedBookingAgent($scenario['agency']);
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

    // Dispatch the issuance job twice — only one set of tickets should be created
    IssueReservationTicketsJob::dispatchSync($scenario['reservation']->id);
    $firstCount = Ticket::query()->where('reservation_id', $scenario['reservation']->id)->count();
    IssueReservationTicketsJob::dispatchSync($scenario['reservation']->id);
    $secondCount = Ticket::query()->where('reservation_id', $scenario['reservation']->id)->count();

    expect($firstCount)->toBeGreaterThan(0)
        ->and($secondCount)->toBe($firstCount)
        ->and($scenario['reservation']->fresh()->ticket_issuance_status)->toBe(TicketIssuanceStatus::Issued)
        ->and($assistance->fresh()->status)->toBe(AssistedBookingStatus::Finalized);
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

function assistedBookingAgent(Agency $agency, string $role = 'agency_agent'): User
{
    $agent = User::factory()->create();
    $agent->forceFill(['agency_id' => $agency->id])->save();
    $agent->assignRole($role);

    return $agent;
}
