<?php

use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use App\Modules\Payments\Providers\MtnMomoAdapter;
use App\Modules\Wallet\Data\WalletTopUpStatus;
use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function mtnReconciliationAdapter(): MtnMomoAdapter
{
    return new MtnMomoAdapter(
        subscriptionKey: 'subscription',
        userId: 'user',
        apiKey: 'api-key',
        environment: 'sandbox',
        callbackUrl: 'https://staging.kora.test/api/v1/payments/webhook/mtn-momo',
        webhookSecret: str_repeat('d', 40),
    );
}

function bindMtnReconciliationAdapter(): void
{
    $manager = new PaymentManager;
    $reflection = new ReflectionClass($manager);
    $reflection->getProperty('resolved')->setValue($manager, ['mtn_momo' => mtnReconciliationAdapter()]);
    app()->instance(PaymentManager::class, $manager);
}

function mtnReconciliationPayment(array $overrides = []): Payment
{
    return Payment::create(array_merge([
        'uuid' => (string) Str::uuid(),
        'method' => PaymentMethod::MobileMoney,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-REC-1',
        'provider_amount' => 5000,
        'provider_currency' => 'EUR',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ], $overrides));
}

function fakeMtnReconciliationVerification(array $payload): void
{
    Http::fake(function (ClientRequest $request) use ($payload) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response($payload, 200);
    });
}

it('confirms a pending wallet payment when the callback was lost', function (): void {
    walletCashEnabled();
    $scenario = walletScenario();
    bindMtnReconciliationAdapter();

    $payment = mtnReconciliationPayment();
    WalletTopUp::create([
        'wallet_id' => $scenario['wallet']->id,
        'payment_id' => $payment->id,
        'amount' => 5000,
        'status' => WalletTopUpStatus::Pending,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    fakeMtnReconciliationVerification([
        'status' => 'SUCCESSFUL',
        'amount' => '5000',
        'currency' => 'EUR',
        'externalId' => $payment->uuid,
        'financialTransactionId' => 'FIN-REC-1',
    ]);

    $this->artisan('payments:reconcile-mtn-momo')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->fresh()->provider_transaction_id)->toBe('FIN-REC-1')
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(5000);
});

it('leaves a payment pending when MTN is unavailable and records the attempt', function (): void {
    bindMtnReconciliationAdapter();

    $payment = mtnReconciliationPayment(['provider_reference' => 'MM-REC-2']);

    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response([], 503);
    });

    $this->artisan('payments:reconcile-mtn-momo')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->last_verified_at)->not->toBeNull();
});

it('never queries terminal payments', function (): void {
    bindMtnReconciliationAdapter();

    mtnReconciliationPayment([
        'provider_reference' => 'MM-REC-TERMINAL',
        'status' => PaymentStatus::Confirmed,
    ]);

    Http::fake();

    $this->artisan('payments:reconcile-mtn-momo')
        ->expectsOutput('No pending MTN MoMo payment to reconcile.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('ignores recently verified and too old payments', function (): void {
    bindMtnReconciliationAdapter();

    $recent = mtnReconciliationPayment([
        'provider_reference' => 'MM-REC-RECENT',
        'last_verified_at' => now()->subMinute(),
    ]);
    $old = mtnReconciliationPayment(['provider_reference' => 'MM-REC-OLD']);
    $old->forceFill([
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ])->save();

    Http::fake();

    $this->artisan('payments:reconcile-mtn-momo')
        ->expectsOutput('No pending MTN MoMo payment to reconcile.')
        ->assertSuccessful();

    Http::assertNothingSent();
    expect($recent->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($old->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('lists candidates without calling MTN during a dry run', function (): void {
    bindMtnReconciliationAdapter();

    $payment = mtnReconciliationPayment(['provider_reference' => 'MM-REC-DRY']);

    Http::fake();

    $this->artisan('payments:reconcile-mtn-momo --dry-run')
        ->expectsOutput($payment->uuid.' pending')
        ->assertSuccessful();

    Http::assertNothingSent();
});
