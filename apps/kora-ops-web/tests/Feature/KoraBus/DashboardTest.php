<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function dashboardReservation(User $user, ?Agency $agency = null): Reservation
{
    return Reservation::create([
        'uuid' => (string) Str::uuid(),
        'reference' => 'RES-'.Str::random(8),
        'selling_agency_id' => $agency?->id,
        'created_by_user_id' => $user->id,
        'status' => ReservationStatus::Confirmed,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'counter',
        'expires_at' => now()->addMinutes(30),
    ]);
}

function confirmedPayment(Reservation $reservation, int $amount, ?string $confirmedAt = null): Payment
{
    return Payment::create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'method' => 'cash',
        'provider' => 'cash',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => $amount,
        'currency' => 'XOF',
        'status' => PaymentStatus::Confirmed,
        'confirmed_at' => $confirmedAt ?? now(),
    ]);
}

it('requires authentication', function (): void {
    $this->get(route('dashboard'))->assertRedirect();
});

it('renders the dashboard with dynamic data and defaults to today', function (): void {
    $user = commercialDirector();
    $reservation = dashboardReservation($user);
    confirmedPayment($reservation, 15000);
    confirmedPayment($reservation, 9000);
    Payment::create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'method' => 'cash',
        'provider' => 'cash',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
        'confirmed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('period.preset', 'today')
            ->has('periodOptions')
            ->where('metrics.sales.value', '24 000 FCFA')
            ->has('metrics.tickets')
            ->has('metrics.cash')
            ->has('metrics.departures')
            ->has('activity.points')
            ->where('activity.bucket', 'hour')
            ->has('priorities')
            ->has('network')
            ->where('heroAction.label', 'Nouvelle vente')
            ->has('quickActions'));
});

it('counts issued tickets and departures of the period', function (): void {
    $user = commercialDirector();
    $reservation = dashboardReservation($user);
    confirmedPayment($reservation, 15000);

    $passenger = ReservationPassenger::create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'first_name' => 'Awa',
        'last_name' => 'Test',
    ]);
    Ticket::create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
        'qr_code' => 'qr-'.Str::random(12),
        'short_code' => 'KORA-1234',
        'status' => TicketStatus::Issued,
        'issued_at' => now(),
    ]);

    [$line] = lineWithStops();
    $bus = Bus::query()->create([
        'uuid' => (string) Str::uuid(),
        'registration_number' => 'TEST-'.Str::random(6),
    ]);
    Departure::query()->create([
        'uuid' => (string) Str::uuid(),
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => now(RevenuePeriod::TIMEZONE)->toDateString(),
        'departure_time' => '18:00',
        'scheduled_at' => now(RevenuePeriod::TIMEZONE)->startOfDay()->addHours(12),
        'status' => 'scheduled',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.tickets.value', '1')
            ->where('metrics.departures.value', '1'));
});

it('scopes payments and tickets to the agency of agency users', function (): void {
    $agency = Agency::factory()->create();
    $otherAgency = Agency::factory()->create();
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_agent');

    confirmedPayment(dashboardReservation($agent, $agency), 10000);
    confirmedPayment(dashboardReservation($agent, $otherAgency), 30000);

    $this->actingAs($agent)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.sales.value', '10 000 FCFA')
            ->where('heroAction.label', 'Nouvelle vente'));
});

it('adapts the hero action to the operations director profile', function (): void {
    $director = User::factory()->create();
    $director->assignRole('operations_director');

    $this->actingAs($director)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('heroAction.label', 'Créer un départ')
            ->where('heroAction.href', route('departures.index')));
});

it('adapts the hero action to the finance profile', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');

    $this->actingAs($daf)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('heroAction.label', 'Voir les recettes')
            ->where('heroAction.href', route('revenue')));
});

it('points the system admin hero to user management and lists admin shortcuts', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('heroAction.label', 'Nouvel utilisateur')
            ->where('heroAction.href', route('users'))
            ->has('quickActions', 4)
            ->where('quickActions.0.href', route('users'))
            ->where('quickActions.1.href', route('roles'))
            ->where('quickActions.2.href', route('administration.settings'))
            ->where('quickActions.3.href', route('terminals')));
});

it('lists agency shortcuts including the upcoming parcel card', function (): void {
    $agency = Agency::factory()->create();
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('quickActions', 4)
            ->where('quickActions.0.href', route('sale'))
            ->where('quickActions.1.href', route('cash'))
            ->where('quickActions.2.href', route('departures.index'))
            ->where('quickActions.3.title', 'Nouveau colis')
            ->where('quickActions.3.href', null));
});

it('lists operations director shortcuts with upcoming screens unlinked', function (): void {
    $director = User::factory()->create();
    $director->assignRole('operations_director');

    $this->actingAs($director)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('quickActions', 4)
            ->where('quickActions.0.href', route('departures.index'))
            ->where('quickActions.1.href', route('fleet.index'))
            ->where('quickActions.2.href', null)
            ->where('quickActions.3.href', null));
});

it('lists finance shortcuts', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');

    $this->actingAs($daf)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('quickActions', 4)
            ->where('quickActions.0.href', route('revenue'))
            ->where('quickActions.1.href', route('fiscal-invoices.index'))
            ->where('quickActions.2.href', route('profitability'))
            ->where('quickActions.3.href', route('expenses.index')));
});

it('lists commercial director shortcuts', function (): void {
    $director = commercialDirector();

    $this->actingAs($director)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('quickActions', 4)
            ->where('quickActions.0.href', route('pricing-rules.index'))
            ->where('quickActions.1.href', route('promotions.index'))
            ->where('quickActions.2.href', route('companies.index'))
            ->where('quickActions.3.href', null));
});

it('filters the dashboard by a custom period', function (): void {
    $user = commercialDirector();
    $reservation = dashboardReservation($user);
    confirmedPayment($reservation, 20000, now()->subDays(10)->toIso8601String());
    confirmedPayment($reservation, 7000);

    $from = now()->subDays(12)->toDateString();
    $to = now()->subDays(8)->toDateString();

    $this->actingAs($user)
        ->get(route('dashboard', ['period' => 'custom', 'from' => $from, 'to' => $to]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('period.preset', 'custom')
            ->where('metrics.sales.value', '20 000 FCFA')
            ->where('activity.bucket', 'day'));
});

it('rejects a custom period without bounds', function (): void {
    $this->actingAs(commercialDirector())
        ->get(route('dashboard', ['period' => 'custom']))
        ->assertSessionHasErrors(['from', 'to']);
});

it('feeds the priority panel with unread ops notifications', function (): void {
    $user = commercialDirector();

    $event = OpsNotificationEvent::create([
        'uuid' => (string) Str::uuid(),
        'dedupe_key' => 'test-'.Str::random(10),
        'type' => 'scan_conflict',
        'severity' => 'critical',
        'title' => 'Conflit de scan',
        'message' => 'Deux scans sur le même siège',
        'occurred_at' => now(),
    ]);
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => OpsNotification::class,
        'data' => ['type' => 'scan_conflict'],
        'audience' => 'ops',
        'event_id' => $event->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('priorities', 1)
            ->where('priorities.0.title', 'Conflit de scan')
            ->where('priorities.0.tone', 'urgent')
            ->where('network.toWatch', '1'));
});

it('feeds the priority panel from the data payload when the notification has no event', function (): void {
    $user = commercialDirector();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => OpsNotification::class,
        'data' => [
            'type' => 'fiscal_credit_note',
            'severity' => 'warning',
            'title' => 'Avoir émis',
            'message' => 'Un avoir a été généré sur la facture.',
        ],
        'audience' => 'ops',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('priorities', 1)
            ->where('priorities.0.title', 'Avoir émis')
            ->where('priorities.0.category', 'Attention')
            ->where('priorities.0.tone', 'control')
            ->where('priorities.0.href', null)
            ->where('network.toWatch', '1'));
});

it('ignores passenger notifications in the priority panel', function (): void {
    $user = commercialDirector();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => OpsNotification::class,
        'data' => ['type' => 'passenger_booking'],
        'audience' => 'passenger',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('priorities', 0)
            ->where('network.toWatch', '0'));
});
