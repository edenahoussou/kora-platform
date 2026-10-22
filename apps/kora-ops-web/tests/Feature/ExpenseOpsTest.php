<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Models\Departure;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Storage::fake('private');
});

it('lets an agency agent create a pending expense with private evidence', function (): void {
    $agency = Agency::factory()->create();
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_agent');
    $category = ExpenseCategory::factory()->create(['code' => 'fuel', 'label' => 'Carburant']);

    $response = $this->actingAs($agent)->post(route('expenses.store'), [
        'category_uuid' => $category->uuid,
        'payment_channel' => 'cash',
        'amount' => 12500,
        'payee_label' => 'Station KORA',
        'description' => 'Plein avant départ',
        'occurred_at' => now()->toDateString(),
        'evidence' => UploadedFile::fake()->image('ticket.jpg'),
    ]);

    $expense = Expense::query()->firstOrFail();
    $response->assertRedirect(route('expenses.index', ['expense' => $expense->uuid]));
    expect($expense->status)->toBe(ExpenseStatus::Pending)
        ->and($expense->reference)->toMatch('/^DEP-\d{8}-[A-Z0-9]{4}$/')
        ->and($expense->hasMedia('evidence'))->toBeTrue()
        ->and($expense->getFirstMedia('evidence')->disk)->toBe('private');
});

it('publishes an expense atomically with its cash ledger movement', function (): void {
    $agency = Agency::factory()->create();
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $manager = User::factory()->create(['agency_id' => $agency->id]);
    $manager->assignRole('agency_manager');
    $session = CashSession::factory()->for($agency)->for($agent, 'agent')->create(['expected_balance' => 50000]);
    $ledger = CashLedger::factory()->for($session, 'cashSession')->create(['expected_balance' => 50000]);
    $expense = Expense::factory()->for($agency)->create([
        'created_by' => $agent->id,
        'cash_session_id' => $session->id,
        'cash_ledger_id' => $ledger->id,
        'amount' => 12500,
    ]);

    $this->actingAs($manager)->post(route('expenses.approve', $expense))->assertRedirect();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Approved)
        ->and($ledger->fresh()->expected_balance)->toBe(37500)
        ->and(CashMovement::query()->where('expense_id', $expense->id)->count())->toBe(1);
});

it('requires a rejection reason and prevents another agency from viewing the expense', function (): void {
    $agency = Agency::factory()->create();
    $otherAgency = Agency::factory()->create();
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $manager = User::factory()->create(['agency_id' => $otherAgency->id]);
    $manager->assignRole('agency_manager');
    $expense = Expense::factory()->for($agency)->create(['created_by' => $agent->id]);

    $this->actingAs($manager)
        ->get(route('expenses.show', $expense))
        ->assertForbidden();

    $agencyManager = User::factory()->create(['agency_id' => $agency->id]);
    $agencyManager->assignRole('agency_manager');
    $this->actingAs($agencyManager)
        ->post(route('expenses.reject', $expense), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Pending);
});

it('lets a network finance user attach a structured cost center and maintenance details', function (): void {
    $agency = Agency::factory()->create();
    $daf = User::factory()->create();
    $daf->assignRole('daf');
    $category = ExpenseCategory::factory()->create(['code' => 'maintenance', 'label' => 'Maintenance']);
    [$line, $origin, $destination] = lineWithStops();
    $bus = Bus::query()->create([
        'uuid' => (string) Str::uuid(),
        'registration_number' => 'BJ-'.Str::random(6),
        'seat_count' => 50,
        'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-10-10',
        'departure_time' => '08:00',
        'status' => 'scheduled',
    ]);

    $response = $this->actingAs($daf)->post(route('expenses.store'), [
        'category_uuid' => $category->uuid,
        'agency_uuid' => $agency->uuid,
        'departure_uuid' => $departure->uuid,
        'payment_channel' => 'cash',
        'amount' => 45_000,
        'payee_label' => 'Garage KORA',
        'description' => 'Réparation boîte de vitesses',
        'occurred_at' => '2026-10-09',
        'evidence' => UploadedFile::fake()->image('facture.jpg'),
        'maintenance' => [
            'type' => 'corrective',
            'garage_label' => 'Garage Akpakpa',
            'parts_amount' => 30_000,
            'labor_amount' => 15_000,
            'immobilized_from' => '2026-10-09',
            'immobilized_to' => '2026-10-10',
        ],
    ]);

    $expense = Expense::query()->firstOrFail();
    $response->assertRedirect(route('expenses.index', ['expense' => $expense->uuid]));

    expect($expense->agency_id)->toBe($agency->id)
        ->and($expense->bus_id)->toBe($bus->id)
        ->and($expense->line_id)->toBe($line->id)
        ->and($expense->departure_id)->toBe($departure->id)
        ->and($expense->maintenanceDetails)->not->toBeNull()
        ->and($expense->maintenanceDetails->maintenance_type->value)->toBe('corrective')
        ->and($expense->maintenanceDetails->garage_label)->toBe('Garage Akpakpa')
        ->and($expense->maintenanceDetails->parts_amount)->toBe(30_000)
        ->and($expense->maintenanceDetails->labor_amount)->toBe(15_000);
});

it('rejects a cost center outside the user perimeter and inconsistent maintenance', function (): void {
    $agency = Agency::factory()->create();
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_agent');
    $fuel = ExpenseCategory::factory()->create(['code' => 'fuel', 'label' => 'Carburant']);
    $maintenance = ExpenseCategory::factory()->create(['code' => 'maintenance', 'label' => 'Maintenance']);
    [$line] = lineWithStops();
    $bus = Bus::query()->create([
        'uuid' => (string) Str::uuid(),
        'registration_number' => 'BJ-'.Str::random(6),
        'seat_count' => 50,
        'status' => 'active',
    ]);
    $otherBus = Bus::query()->create([
        'uuid' => (string) Str::uuid(),
        'registration_number' => 'BJ-'.Str::random(6),
        'seat_count' => 50,
        'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-10-10',
        'departure_time' => '08:00',
        'status' => 'scheduled',
    ]);

    $this->actingAs($agent)->post(route('expenses.store'), [
        'category_uuid' => $fuel->uuid,
        'bus_uuid' => $bus->uuid,
        'payment_channel' => 'cash',
        'amount' => 12_500,
        'payee_label' => 'Station KORA',
        'occurred_at' => '2026-10-09',
        'evidence' => UploadedFile::fake()->image('ticket.jpg'),
    ])->assertSessionHasErrors('bus_uuid');

    $daf = User::factory()->create();
    $daf->assignRole('daf');

    $this->actingAs($daf)->post(route('expenses.store'), [
        'category_uuid' => $fuel->uuid,
        'agency_uuid' => $agency->uuid,
        'bus_uuid' => $bus->uuid,
        'departure_uuid' => $departure->uuid,
        'payment_channel' => 'cash',
        'amount' => 12_500,
        'payee_label' => 'Station KORA',
        'occurred_at' => '2026-10-09',
        'evidence' => UploadedFile::fake()->image('ticket.jpg'),
    ])->assertSessionHasErrors('bus_uuid');

    $this->actingAs($daf)->post(route('expenses.store'), [
        'category_uuid' => $fuel->uuid,
        'agency_uuid' => $agency->uuid,
        'departure_uuid' => $departure->uuid,
        'bus_uuid' => $otherBus->uuid,
        'payment_channel' => 'cash',
        'amount' => 12_500,
        'payee_label' => 'Station KORA',
        'occurred_at' => '2026-10-09',
        'evidence' => UploadedFile::fake()->image('ticket.jpg'),
    ])->assertSessionHasErrors('bus_uuid');

    $this->actingAs($daf)->post(route('expenses.store'), [
        'category_uuid' => $fuel->uuid,
        'agency_uuid' => $agency->uuid,
        'payment_channel' => 'cash',
        'amount' => 12_500,
        'payee_label' => 'Station KORA',
        'occurred_at' => '2026-10-09',
        'evidence' => UploadedFile::fake()->image('ticket.jpg'),
        'maintenance' => ['type' => 'preventive'],
    ])->assertSessionHasErrors('maintenance');

    expect(Expense::query()->count())->toBe(0);
});
