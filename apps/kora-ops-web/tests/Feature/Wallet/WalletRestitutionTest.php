<?php

use App\Models\User;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Models\WalletRestitution;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Notification::fake();
    $this->seed(KoraBusRolePermissionSeeder::class);
});

it('runs the restitution workflow and debits the wallet once', function (): void {
    $scenario = walletScenario(['cash' => 10000]);
    $requester = User::factory()->create();
    $requester->assignRole('agency_agent');
    $approver = User::factory()->create();
    $approver->assignRole('daf');

    Sanctum::actingAs($requester);
    $response = $this->postJson('/api/v1/ops/wallet-restitutions', [
        'wallet_uuid' => $scenario['wallet']->uuid,
        'amount' => 4000,
        'note' => 'Erreur de recharge',
    ])->assertCreated();

    $uuid = $response->json('data.uuid');
    expect($response->json('data.status'))->toBe(RestitutionStatus::Requested->value);

    Sanctum::actingAs($approver);
    $this->postJson('/api/v1/ops/wallet-restitutions/'.$uuid.'/approve')->assertOk();
    $this->postJson('/api/v1/ops/wallet-restitutions/'.$uuid.'/mark-paid', [
        'payout_reference' => 'MM-2026-0001',
        'payout_channel' => 'mobile_money',
    ])->assertOk();

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(6000)
        ->and(WalletRestitution::query()->firstOrFail()->status)->toBe(RestitutionStatus::Paid);

    $this->postJson('/api/v1/ops/wallet-restitutions/'.$uuid.'/mark-paid', [
        'payout_reference' => 'MM-2026-0001',
    ])->assertUnprocessable();

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(6000);
});

it('rejects a restitution above the available balance', function (): void {
    $scenario = walletScenario(['cash' => 1000]);
    $requester = User::factory()->create();
    $requester->assignRole('agency_agent');

    Sanctum::actingAs($requester);
    $this->postJson('/api/v1/ops/wallet-restitutions', [
        'wallet_uuid' => $scenario['wallet']->uuid,
        'amount' => 5000,
    ])->assertUnprocessable()->assertJsonValidationErrors(['amount']);
});

it('forbids approving without the restitution permission', function (): void {
    $scenario = walletScenario(['cash' => 5000]);
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $restitution = WalletRestitution::query()->create([
        'wallet_id' => $scenario['wallet']->getKey(),
        'amount' => 2000,
        'status' => RestitutionStatus::Requested,
        'requested_by_user_id' => $agent->getKey(),
    ]);

    Sanctum::actingAs($agent);
    $this->postJson('/api/v1/ops/wallet-restitutions/'.$restitution->uuid.'/approve')->assertForbidden();
});

it('records an audited wallet adjustment', function (): void {
    $scenario = walletScenario();
    $daf = User::factory()->create();
    $daf->assignRole('daf');

    Sanctum::actingAs($daf);
    $this->postJson('/api/v1/ops/wallets/'.$scenario['wallet']->uuid.'/adjustments', [
        'balance_type' => 'points',
        'direction' => 'credit',
        'amount' => 150,
        'reason' => 'Geste commercial validé',
    ])->assertCreated();

    expect($scenario['wallet']->fresh()->points_balance)->toBe(150);
});
