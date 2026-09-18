<?php

use App\Models\User;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
