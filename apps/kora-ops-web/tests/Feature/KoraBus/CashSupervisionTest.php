<?php

use App\Models\User;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');
    Storage::fake('private');
    $this->seed(KoraBusRolePermissionSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array{agency: Agency, manager: User, agent: User} */
function supervisionScenario(): array
{
    $agency = Agency::query()->create([
        'code' => 'SUP-AGENCY',
        'name' => 'Agence Supervision',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $manager = User::factory()->create(['agency_id' => $agency->id]);
    $manager->assignRole('agency_manager');
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_agent');

    return compact('agency', 'manager', 'agent');
}

function supervisionSession(User $agent, Agency $agency, string $businessDate, string $status = 'closed'): CashSession
{
    $session = CashSession::factory()->create([
        'agent_id' => $agent->id,
        'agency_id' => $agency->id,
        'business_date' => $businessDate,
        'status' => $status,
        'opened_at' => Carbon::parse($businessDate.' 07:30:00'),
        'closed_at' => $status === 'closed' ? Carbon::parse($businessDate.' 18:00:00') : null,
    ]);
    CashLedger::factory()->create([
        'cash_session_id' => $session->id,
        'ledger_key' => 'cash',
        'kind' => 'cash',
        'status' => $status === 'closed' ? 'closed' : 'open',
        'opened_by' => $agent->id,
        'opened_at' => Carbon::parse($businessDate.' 07:30:00'),
    ]);

    return $session->load('ledgers');
}

it('lists every agency operation for a manager but only personal ones for an agent', function (): void {
    $scenario = supervisionScenario();
    $colleague = User::factory()->create(['agency_id' => $scenario['agency']->id]);
    $own = supervisionSession($scenario['agent'], $scenario['agency'], '2026-08-17');
    $other = supervisionSession($colleague, $scenario['agency'], '2026-08-18');
    $foreignAgency = Agency::factory()->create();
    supervisionSession(User::factory()->create(['agency_id' => $foreignAgency->id]), $foreignAgency, '2026-08-18');

    $this->actingAs($scenario['manager'])->get(route('cash'))
        ->assertInertia(fn ($page) => $page
            ->component('cash')
            ->where('operations.total', 4) // 2 ouvertures + 2 clôtures
            ->where('permissions.canViewAgency', true)
            ->missing('operations.data.0.id'));

    $this->actingAs($scenario['agent'])->get(route('cash'))
        ->assertInertia(fn ($page) => $page
            ->where('operations.total', 2)
            ->where('operations.data.0.sessionUuid', $own->uuid)
            ->where('permissions.canViewAgency', false));

    $this->actingAs($scenario['manager'])->get(route('cash', [
        'from' => '2026-08-18', 'to' => '2026-08-18',
    ]))->assertInertia(fn ($page) => $page
        ->where('operations.total', 2)
        ->where('operations.data.0.sessionUuid', $other->uuid));

    $this->actingAs($scenario['manager'])->get(route('cash', [
        'agent' => $scenario['agent']->uuid,
    ]))->assertInertia(fn ($page) => $page->where('operations.total', 2));
});

it('lets a manager inspect a session detail via session_detail param but forbids an outsider', function (): void {
    $scenario = supervisionScenario();
    $session = supervisionSession($scenario['agent'], $scenario['agency'], '2026-08-18');
    CashMovement::factory()->create([
        'cash_ledger_id' => $session->ledgers->first()->id,
        'reference' => 'VENTE-SUPERVISEE',
    ]);

    $this->actingAs($scenario['manager'])
        ->get(route('cash', ['session_detail' => $session->uuid]))
        ->assertInertia(fn ($page) => $page
            ->component('cash')
            ->where('sessionDetail.uuid', $session->uuid)
            ->where('sessionDetail.agentName', $scenario['agent']->name)
            ->where('operations.total', 3) // ouverture + clôture + mouvement
            ->missing('sessionDetail.id'));

    $foreignManager = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);
    $foreignManager->assignRole('agency_manager');
    $this->actingAs($foreignManager)
        ->get(route('cash', ['session_detail' => $session->uuid]))
        ->assertForbidden();
});

it('surfaces a session left open instead of an empty cash page', function (): void {
    $scenario = supervisionScenario();
    $stale = supervisionSession($scenario['agent'], $scenario['agency'], '2026-08-18', 'open');

    $this->actingAs($scenario['agent'])->get(route('cash'))
        ->assertInertia(fn ($page) => $page
            ->where('cashSession.uuid', $stale->uuid)
            ->where('cashSession.isStale', true)
            ->has('operations.data', 1)
            ->where('operations.data.0.kind', 'lifecycle')
            ->where('operations.data.0.event', 'session_opened'));
});

it('exposes movements and lifecycle events side by side in the unified journal', function (): void {
    $scenario = supervisionScenario();
    $session = supervisionSession($scenario['agent'], $scenario['agency'], '2026-08-18');
    CashMovement::factory()->pendingTransfer()->create([
        'cash_ledger_id' => $session->ledgers->first()->id,
        'reference' => 'REV-PENDING-1',
        'occurred_at' => Carbon::parse('2026-08-18 15:00:00'),
    ]);

    $this->actingAs($scenario['manager'])
        ->get(route('cash'))
        ->assertInertia(fn ($page) => $page
            ->has('operations.data', 3)
            ->where('operations.data.0.event', 'session_closed')
            ->where('operations.data.1.event', 'reversement')
            ->where('operations.data.1.status', 'pending')
            ->where('operations.data.2.event', 'session_opened'));

    $this->actingAs($scenario['manager'])
        ->get(route('cash', ['nature' => 'movements']))
        ->assertInertia(fn ($page) => $page
            ->has('operations.data', 1)
            ->where('operations.data.0.reference', 'REV-PENDING-1'));

    $this->actingAs($scenario['manager'])
        ->get(route('cash', ['nature' => 'lifecycle']))
        ->assertInertia(fn ($page) => $page->has('operations.data', 2));
});

it('lets a manager force close a stale session and reject its pending movements', function (): void {
    $scenario = supervisionScenario();
    $session = supervisionSession($scenario['agent'], $scenario['agency'], '2026-08-18', 'open');
    $ledger = $session->ledgers->first();
    $pending = CashMovement::factory()->pendingTransfer()->create(['cash_ledger_id' => $ledger->id]);
    $posted = CashMovement::factory()->create(['cash_ledger_id' => $ledger->id]);

    $this->actingAs($scenario['manager'])
        ->post(route('cash.sessions.force-close', $session), [
            'reason' => 'Agent absent, caisse restée ouverte.',
        ])
        ->assertRedirect();

    $session->refresh();
    expect($session->status->value)->toBe('closed')
        ->and($session->force_closed_by)->toBe($scenario['manager']->id)
        ->and($session->force_closed_at)->not->toBeNull()
        ->and($session->ledgers()->first()->status->value)->toBe('closed')
        ->and($session->ledgers()->first()->actual_balance)->toBeNull()
        ->and($pending->fresh()->status->value)->toBe('rejected')
        ->and($posted->fresh()->status->value)->toBe('posted');

    expect(OpsNotificationEvent::query()->where('type', 'cash_session_force_closed')->exists())->toBeTrue();
    expect(Activity::query()->where('event', 'session_force_closed')->exists())->toBeTrue();
});

it('forbids an agent to force close their own session', function (): void {
    $scenario = supervisionScenario();
    $session = supervisionSession($scenario['agent'], $scenario['agency'], '2026-08-18', 'open');

    $this->actingAs($scenario['agent'])
        ->post(route('cash.sessions.force-close', $session), [
            'reason' => 'Tentative non autorisée.',
        ])
        ->assertForbidden();

    expect($session->fresh()->status->value)->toBe('open');
});

it('flags unclosed sessions through the scheduled command once a day', function (): void {
    $scenario = supervisionScenario();
    supervisionSession($scenario['agent'], $scenario['agency'], '2026-08-18', 'open');
    $colleague = User::factory()->create(['agency_id' => $scenario['agency']->id]);
    supervisionSession($colleague, $scenario['agency'], '2026-08-19', 'open');

    $this->artisan('cash:flag-unclosed-sessions')->assertSuccessful();
    $this->artisan('cash:flag-unclosed-sessions')->assertSuccessful();

    expect(OpsNotificationEvent::query()->where('type', 'cash_session_left_open')->count())->toBe(1);

    Carbon::setTestNow('2026-08-20 06:30:00');
    $this->artisan('cash:flag-unclosed-sessions')->assertSuccessful();

    // Les deux sessions sont désormais en retard : deux nouvelles alertes du jour.
    expect(OpsNotificationEvent::query()->where('type', 'cash_session_left_open')->count())->toBe(3);
});

it('lets a supervisor export the movements of any agency session', function (): void {
    $scenario = supervisionScenario();
    $session = supervisionSession($scenario['agent'], $scenario['agency'], '2026-08-18');
    CashMovement::factory()->create([
        'cash_ledger_id' => $session->ledgers->first()->id,
        'reference' => 'VENTE-HISTORIQUE',
    ]);

    $csv = $this->actingAs($scenario['manager'])
        ->get(route('cash.export', ['session_uuid' => $session->uuid]))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('VENTE-HISTORIQUE');
});

it('exposes theoretical agency balances even without an open session', function (): void {
    $scenario = supervisionScenario();
    CashRegister::factory()->create(['agency_id' => $scenario['agency']->id]);

    $this->actingAs($scenario['manager'])->get(route('cash'))
        ->assertInertia(fn ($page) => $page
            ->where('cashSession', null)
            ->has('agencyBalances.registers', 1)
            ->where('agencyBalances.registers.0.published', 0));
});
