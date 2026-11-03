<?php

use App\Modules\Payments\Actions\ConfirmPaymentAction;
use App\Modules\Payments\PaymentManager;
use App\Modules\Payments\Providers\Fake\FakePaymentProvider;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Models\WalletTopUp;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->app->bind(PaymentManager::class, function (): PaymentManager {
        $manager = new PaymentManager;

        $reflection = new ReflectionClass($manager);
        $reflection->getProperty('resolved')->setValue($manager, [
            'fedapay' => new FakePaymentProvider,
            'mtn_momo' => new FakePaymentProvider,
            'kkiapay' => new FakePaymentProvider,
            'fake' => new FakePaymentProvider,
        ]);

        return $manager;
    });

    FakePaymentProvider::reset();
});

function walletTopUpPayload(array $overrides = []): array
{
    return array_merge([
        'amount' => 5000,
        'method' => 'mobile_money',
        'provider' => 'fedapay',
        'phone' => '+2290197421860',
        'mobile_money_network' => 'mtn_momo',
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides);
}

it('rejects a top-up while the cash wallet is disabled', function (): void {
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/wallet/topups', walletTopUpPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['wallet']);
});

it('initiates a provider top-up and credits the wallet once confirmed', function (): void {
    walletCashEnabled();
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $response = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload())->assertCreated();

    $topup = WalletTopUp::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
    expect($topup->status->value)->toBe('pending')
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(0);

    $payment = $topup->payment;
    app(ConfirmPaymentAction::class)->handle($payment, 'success', 'PROVIDER-REF');

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(5000)
        ->and($topup->fresh()->status->value)->toBe('confirmed');
});

it('replays the provider confirmation without double crediting', function (): void {
    walletCashEnabled();
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $response = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload())->assertCreated();
    $topup = WalletTopUp::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
    $confirm = app(ConfirmPaymentAction::class);

    $confirm->handle($topup->payment, 'success', 'PROVIDER-REF');
    $confirm->handle($topup->payment->fresh(), 'success', 'PROVIDER-REF');

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(5000);
});

it('marks the top-up failed when the provider payment fails', function (): void {
    walletCashEnabled();
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $response = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload())->assertCreated();
    $topup = WalletTopUp::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();

    app(ConfirmPaymentAction::class)->handle($topup->payment, 'failed');

    expect($topup->fresh()->status->value)->toBe('failed')
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(0);
});

it('enforces the configured top-up bounds', function (): void {
    walletCashEnabled();
    $settings = app(WalletSettings::class);
    $settings->topupMaxAmount = 3000;
    $settings->save();

    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/wallet/topups', walletTopUpPayload(['amount' => 5000]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('exposes the top-up status of the authenticated traveler', function (): void {
    walletCashEnabled();
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $response = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload())->assertCreated();

    $this->getJson('/api/v1/wallet/topups/'.$response->json('data.uuid'))
        ->assertOk()
        ->assertJsonPath('data.amount', 5000)
        ->assertJsonPath('data.status', 'pending');
});
