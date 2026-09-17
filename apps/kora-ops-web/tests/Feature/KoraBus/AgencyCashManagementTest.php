<?php

use App\Models\User;
use App\Modules\Cash\Actions\ApproveCashTransferAction;
use App\Modules\Cash\Actions\CreateCashTransferAction;
use App\Modules\Cash\Actions\EnsureAgencyCashRegistersAction;
use App\Modules\Cash\Actions\OpenCashSessionAction;
use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Data\CashRegisterKind;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Services\CashRegisterBalance;
use App\Modules\IdentityAccess\Actions\CreateAgencyAction;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Storage::fake('private');
});

it('provisions the vault when an agency is created through the application action', function (): void {
    $agency = app(CreateAgencyAction::class)->execute([
        'name' => 'Agence Nord',
        'city' => 'Parakou',
        'country' => 'BJ',
        'head_user_id' => null,
        'status' => 'active',
    ]);

    expect($agency->cashRegisters()->where('kind', CashRegisterKind::Vault->value)->exists())->toBeTrue();
});

it('renders the agency cash supervision page with public identifiers only', function (): void {
    $agency = Agency::factory()->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);
    $user->assignRole('system_admin');

    $this->actingAs($user)
        ->get(route('agencies.show', $agency))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('agencies/detail')
            ->where('agency.uuid', $agency->uuid)
            ->has('cashRegisters', 1)
            ->where('cashRegisters.0.kind', 'vault')
            ->missing('cashRegisters.0.id')
            ->missing('cashRegisters.0.agencyId')
            ->missing('cashRegisters.0.authorizedUsers.0.id'));
});

it('creates one stable local vault for an agency and keeps it idempotent', function (): void {
    $agency = Agency::factory()->create();
    $action = app(EnsureAgencyCashRegistersAction::class);

    $first = $action->execute($agency);
    $second = $action->execute($agency);

    expect($first->uuid)->toBe($second->uuid)
        ->and(CashRegister::query()->where('agency_id', $agency->id)->where('kind', CashRegisterKind::Vault->value)->count())->toBe(1)
        ->and(app(CashRegisterBalance::class)->published($first))->toBe(0);
});

it('sums two counter balances into the agency cash total', function (): void {
    $agency = Agency::factory()->create();
    $counterOne = CashRegister::factory()->for($agency)->create(['code' => 'G-1']);
    $counterTwo = CashRegister::factory()->for($agency)->create(['code' => 'G-2']);

    postedMovement($counterOne, 20000);
    postedMovement($counterTwo, 30000);

    expect(app(CashRegisterBalance::class)->agencyTotals($agency->id))->toMatchArray([
        'cash' => 50000,
        'global' => 50000,
    ]);
});

it('allows only one active session per counter and only its assigned agent', function (): void {
    $agency = Agency::factory()->create();
    $counter = CashRegister::factory()->for($agency)->create(['code' => 'G-1']);
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $otherAgent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_agent');
    $otherAgent->assignRole('agency_agent');
    $counter->authorizedUsers()->sync([$agent->id]);
    $opening = [[
        'kind' => 'cash',
        'account_uuid' => null,
        'opening_balance' => 0,
        'opening_comment' => null,
        'evidence' => UploadedFile::fake()->image('opening.jpg'),
        'evidence_captured_at' => now()->toISOString(),
    ]];

    app(OpenCashSessionAction::class)->execute($agent, [
        'cash_register_uuid' => $counter->uuid,
        'ledgers' => $opening,
    ]);

    expect(fn () => app(OpenCashSessionAction::class)->execute($otherAgent, [
        'cash_register_uuid' => $counter->uuid,
        'ledgers' => $opening,
    ]))->toThrow(ValidationException::class);
    expect(fn () => app(OpenCashSessionAction::class)->execute($agent, [
        'cash_register_uuid' => $counter->uuid,
        'ledgers' => $opening,
    ]))->toThrow(ValidationException::class);
});

it('moves cash from a counter to the vault without changing the agency total', function (): void {
    $agency = Agency::factory()->create();
    $counter = CashRegister::factory()->for($agency)->create(['code' => 'G-1']);
    $vault = CashRegister::factory()->for($agency)->vault()->create();
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_manager');
    postedMovement($counter, 50000);

    $create = app(CreateCashTransferAction::class);
    $transfer = $create->execute($agent, [
        'source_cash_register_uuid' => $counter->uuid,
        'destination_cash_register_uuid' => $vault->uuid,
        'amount' => 20000,
        'currency' => 'XOF',
        'description' => 'Dépôt de recette',
    ]);

    expect($transfer->status->value)->toBe('pending')
        ->and(app(CashRegisterBalance::class)->reserved($counter))->toBe(20000)
        ->and(app(CashRegisterBalance::class)->agencyTotals($agency->id)['cash'])->toBe(50000);

    app(ApproveCashTransferAction::class)->execute($transfer, $agent);

    expect(CashMovement::query()->where('cash_transfer_id', $transfer->id)->count())->toBe(2)
        ->and(app(CashRegisterBalance::class)->agencyTotals($agency->id)['cash'])->toBe(50000);
});

it('decreases the agency total for a direction reversement and accepts direction funding', function (): void {
    $agency = Agency::factory()->create();
    $counter = CashRegister::factory()->for($agency)->create(['code' => 'G-1']);
    $vault = CashRegister::factory()->for($agency)->vault()->create();
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_manager');
    postedMovement($counter, 50000);

    $create = app(CreateCashTransferAction::class);
    $reversement = $create->execute($agent, [
        'source_cash_register_uuid' => $counter->uuid,
        'destination_type' => 'direction',
        'amount' => 10000,
    ]);
    app(ApproveCashTransferAction::class)->execute($reversement, $agent);
    expect(app(CashRegisterBalance::class)->agencyTotals($agency->id)['cash'])->toBe(40000);

    $funding = $create->execute($agent, [
        'destination_cash_register_uuid' => $vault->uuid,
        'destination_type' => 'direction',
        'amount' => 15000,
    ]);
    app(ApproveCashTransferAction::class)->execute($funding, $agent);

    expect(app(CashRegisterBalance::class)->agencyTotals($agency->id)['cash'])->toBe(55000)
        ->and(CashMovement::query()->where('cash_transfer_id', $funding->id)->count())->toBe(1);
});

it('rejects direct counter to counter transfers and reserves pending exits', function (): void {
    $agency = Agency::factory()->create();
    $counterOne = CashRegister::factory()->for($agency)->create(['code' => 'G-1']);
    $counterTwo = CashRegister::factory()->for($agency)->create(['code' => 'G-2']);
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_manager');
    postedMovement($counterOne, 10000);

    expect(fn () => app(CreateCashTransferAction::class)->execute($agent, [
        'source_cash_register_uuid' => $counterOne->uuid,
        'destination_cash_register_uuid' => $counterTwo->uuid,
        'amount' => 1000,
    ]))->toThrow(ValidationException::class);

    $transfer = app(CreateCashTransferAction::class)->execute($agent, [
        'source_cash_register_uuid' => $counterOne->uuid,
        'destination_type' => 'direction',
        'amount' => 10000,
    ]);
    expect(app(CashRegisterBalance::class)->available($counterOne))->toBe(0);
    expect(fn () => app(CreateCashTransferAction::class)->execute($agent, [
        'source_cash_register_uuid' => $counterOne->uuid,
        'destination_type' => 'direction',
        'amount' => 1,
    ]))->toThrow(ValidationException::class);
    expect($transfer->movements)->toBeEmpty();
});

it('keeps published transfer legs immutable and does not create orphan movements', function (): void {
    $agency = Agency::factory()->create();
    $counter = CashRegister::factory()->for($agency)->create(['code' => 'G-1']);
    $vault = CashRegister::factory()->for($agency)->vault()->create();
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_manager');
    postedMovement($counter, 10000);

    $transfer = app(CreateCashTransferAction::class)->execute($agent, [
        'source_cash_register_uuid' => $counter->uuid,
        'destination_cash_register_uuid' => $vault->uuid,
        'amount' => 5000,
    ]);
    $approved = app(ApproveCashTransferAction::class)->execute($transfer, $agent);
    $movement = $approved->movements->firstOrFail();

    expect(CashMovement::query()->whereNull('cash_transfer_id')->where('type', 'cash_transfer')->count())->toBe(0);
    expect(fn () => $movement->update(['amount' => 1]))->toThrow(LogicException::class);
});

function postedMovement(CashRegister $register, int $amount): CashMovement
{
    return CashMovement::query()->create([
        'cash_register_id' => $register->id,
        'type' => 'cash_receipt',
        'direction' => CashMovementDirection::In,
        'status' => CashMovementStatus::Posted,
        'amount' => $amount,
        'currency' => 'XOF',
        'description' => 'Recette de démonstration',
        'occurred_at' => now(),
    ]);
}
