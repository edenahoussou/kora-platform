<?php

use App\Models\User;
use App\Modules\Payments\Actions\ConfirmPaymentAction;
use App\Modules\Payments\Contracts\PaymentProvider;
use App\Modules\Payments\Contracts\ProviderResponse;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use App\Modules\Payments\Providers\Fake\FakePaymentProvider;
use App\Modules\Payments\Providers\FedaPayAdapter;
use App\Modules\Payments\Providers\KKiaPayAdapter;
use App\Modules\Payments\Providers\MtnMomoAdapter;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Actions\ReconcileWalletTopUpAction;
use App\Modules\Wallet\Models\WalletTopUp;
use FedaPay\HttpClient\ClientInterface;
use FedaPay\Requestor;
use Illuminate\Support\Facades\Http;
use Kkiapay\Kkiapay;
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

it('binds top-up keys to the wallet and to the normalized request including aliases', function (): void {
    walletCashEnabled();
    $first = walletForUser(User::factory()->create());
    $second = walletForUser(User::factory()->create());
    Sanctum::actingAs($first->user);
    $payload = walletTopUpPayload();
    $uuid = $this->postJson('/api/v1/wallet/topups', $payload)->assertCreated()->json('data.uuid');

    $this->postJson('/api/v1/wallet/topups', array_replace($payload, ['phone' => '01 97 42 18 60']))
        ->assertOk()->assertJsonPath('data.uuid', $uuid);

    $alias = array_replace($payload, ['idempotency_key' => 'alias']);
    $this->postJson('/api/v1/wallet/topups', $alias)->assertOk()->assertJsonPath('data.uuid', $uuid);
    $this->postJson('/api/v1/wallet/topups', array_replace($alias, ['amount' => 6000]))->assertConflict();

    Sanctum::actingAs($second->user);
    $otherUuid = $this->postJson('/api/v1/wallet/topups', $payload)->assertCreated()->json('data.uuid');
    expect($otherUuid)->not->toBe($uuid);
    $paymentUuid = WalletTopUp::query()->where('uuid', $uuid)->firstOrFail()->payment->uuid;
    $this->getJson("/api/v1/wallet/topups/{$uuid}")->assertNotFound();
    $this->getJson("/api/v1/payments/{$paymentUuid}")->assertForbidden();
    $this->postJson("/api/v1/payments/{$paymentUuid}/verify", ['transaction_id' => 'untrusted'])->assertForbidden();
    $this->postJson("/api/v1/payments/{$paymentUuid}/cancel")->assertForbidden();
});

it('checks pending amounts against the cap and allows a new key after a proven failure', function (): void {
    walletCashEnabled();
    $settings = app(WalletSettings::class);
    $settings->cashBalanceCap = 7000;
    $settings->save();
    Sanctum::actingAs(User::factory()->create());

    $payload = walletTopUpPayload();
    $uuid = $this->postJson('/api/v1/wallet/topups', $payload)->assertCreated()->json('data.uuid');
    $this->postJson('/api/v1/wallet/topups', walletTopUpPayload(['amount' => 4000]))->assertUnprocessable();
    $payment = WalletTopUp::query()->where('uuid', $uuid)->firstOrFail()->payment;
    $this->postJson("/api/v1/payments/{$payment->uuid}/cancel")->assertUnprocessable();
    app(ConfirmPaymentAction::class)->handle($payment, 'failed');

    $this->postJson('/api/v1/wallet/topups', $payload)->assertOk()->assertJsonPath('data.payment.next_action', 'retry');
    $this->postJson('/api/v1/wallet/topups', walletTopUpPayload())->assertCreated();
});

it('opens a real KKiaPay adapter widget on creation and only credits the verified amount', function (): void {
    walletCashEnabled();
    $sdk = Mockery::mock(Kkiapay::class);
    $manager = app(PaymentManager::class);
    (new ReflectionClass($manager))->getProperty('resolved')->setValue($manager, [
        'kkiapay' => new KKiaPayAdapter($sdk, 'public-key', 'sandbox', webhookSecret: 'webhook-key'),
    ]);
    $this->instance(PaymentManager::class, $manager);
    $wallet = walletForUser(User::factory()->create());
    Sanctum::actingAs($wallet->user);

    $body = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload(['provider' => 'kkiapay']))
        ->assertCreated()->assertJsonPath('data.payment.next_action', 'open_widget')
        ->assertJsonPath('data.payment.widget.amount', 5000)->json('data');
    $paymentUuid = $body['payment']['uuid'];
    expect($body['payment']['widget']['partnerId'])->toBe($paymentUuid);
    $sdk->shouldReceive('verifyTransaction')->with('invalid')->andReturn((object) ['status' => 'SUCCESS', 'amount' => 4900, 'partnerId' => $paymentUuid]);
    $sdk->shouldReceive('verifyTransaction')->with('valid')->andReturn((object) ['status' => 'SUCCESS', 'amount' => 5000, 'partnerId' => $paymentUuid]);

    $this->postJson("/api/v1/payments/{$paymentUuid}/verify", ['transaction_id' => 'invalid'])
        ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.provider_transaction_id', null);
    $this->postJson("/api/v1/payments/{$paymentUuid}/verify", ['transaction_id' => 'valid'])
        ->assertOk()->assertJsonPath('data.next_action', 'done')->assertJsonPath('data.reservation', null);
    $event = ['transactionId' => 'valid', 'partnerId' => $paymentUuid, 'amount' => 5000];
    $this->postJson('/api/v1/payments/webhook/kkiapay', $event, ['x-kkiapay-secret' => 'invalid'])->assertForbidden();
    $this->postJson('/api/v1/payments/webhook/kkiapay', $event, ['x-kkiapay-secret' => 'webhook-key'])->assertOk();
    expect($wallet->fresh()->cash_balance)->toBe(5000)
        ->and($wallet->transactions()->count())->toBe(1);
});

it('resumes a FedaPay checkout on the same collection after token generation fails', function (): void {
    walletCashEnabled();
    $client = Mockery::mock(ClientInterface::class);
    Requestor::setHttpClient($client);
    $adapter = new FedaPayAdapter('test-key', 'sandbox', null);
    $manager = app(PaymentManager::class);
    (new ReflectionClass($manager))->getProperty('resolved')->setValue($manager, ['fedapay' => $adapter]);
    $this->instance(PaymentManager::class, $manager);

    $collection = [
        'id' => 34, 'klass' => 'v1/transaction', 'reference' => 'FEDA-34', 'amount' => 5000,
        'status' => 'pending', 'currency' => ['iso' => 'XOF'],
    ];
    $client->shouldReceive('request')->once()->withArgs(function ($method, $url, $params) use (&$collection): bool {
        if ($method !== 'post' || ! str_ends_with($url, '/v1/transactions')) {
            return false;
        }
        expect($params['description'])->toContain('recharge')
            ->and($params['custom_metadata'])->toHaveKey('kora_wallet_topup_uuid')
            ->not->toHaveKey('kora_reservation_uuid');
        $collection['custom_metadata'] = $params['custom_metadata'];

        return true;
    })->andReturnUsing(fn () => [json_encode(['v1/transaction' => array_merge($collection)]), 200, []]);
    $client->shouldReceive('request')->withArgs(fn ($method, $url) => $method === 'post' && str_ends_with($url, '/34/token'))
        ->twice()->andReturn([json_encode(['message' => 'unavailable']), 503, []], [json_encode(['url' => 'https://checkout.example.test/FEDA-34']), 200, []]);
    $client->shouldReceive('request')->withArgs(fn ($method, $url) => $method === 'get' && str_ends_with($url, '/34'))
        ->twice()->andReturnUsing(function () use (&$collection): array {
            return [json_encode(['v1/transaction' => $collection]), 200, []];
        });

    Sanctum::actingAs(User::factory()->create());
    try {
        $payload = walletTopUpPayload(['method' => 'card', 'phone' => null, 'mobile_money_network' => null]);
        $uuid = $this->postJson('/api/v1/wallet/topups', $payload)
            ->assertCreated()->assertJsonPath('data.payment.failure.retryable', false)
            ->assertJsonPath('data.payment.provider_reference', 'FEDA-34')->json('data.uuid');
        $topup = WalletTopUp::query()->where('uuid', $uuid)->firstOrFail();
        expect($topup->payment->provider_transaction_id)->toBe('34');
        app(ReconcileWalletTopUpAction::class)->recover($topup);
        $this->postJson('/api/v1/wallet/topups', $payload)
            ->assertOk()->assertJsonPath('data.payment.next_action', 'open_url')
            ->assertJsonPath('data.payment.checkout_url', 'https://checkout.example.test/FEDA-34');
    } finally {
        Requestor::setHttpClient(null);
    }
});

it('uses a stable MTN MoMo reference and verifies the provider currency', function (): void {
    walletCashEnabled();
    $manager = app(PaymentManager::class);
    (new ReflectionClass($manager))->getProperty('resolved')->setValue($manager, [
        'mtn_momo' => new MtnMomoAdapter('subscription', 'user', 'api-key', 'sandbox'),
    ]);
    $this->instance(PaymentManager::class, $manager);
    $wallet = walletForUser(User::factory()->create());
    Sanctum::actingAs($wallet->user);
    $currency = 'XOF';
    Http::fake(function ($request) use (&$currency) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'test-token']);
        }

        if ($request->method() === 'POST') {
            expect($request->header('X-Reference-Id')[0])->toBe($request['externalId'])
                ->and($request['payerMessage'])->toContain('recharge');

            return Http::response([], 202);
        }

        return Http::response([
            'status' => 'SUCCESSFUL', 'amount' => '5000', 'currency' => $currency,
            'externalId' => basename($request->url()),
        ]);
    });

    $payment = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload(['provider' => 'mtn_momo']))
        ->assertCreated()->assertJsonPath('data.payment.next_action', 'verify')->json('data.payment');
    $uuid = $payment['uuid'];
    expect($payment['provider_reference'])->toBe($uuid);
    $this->postJson("/api/v1/payments/{$uuid}/verify")->assertOk()->assertJsonPath('data.status', 'pending');
    $currency = 'EUR';
    $this->postJson("/api/v1/payments/{$uuid}/verify")->assertOk()->assertJsonPath('data.next_action', 'done');
    expect($wallet->fresh()->cash_balance)->toBe(5000);
});

it('only closes proven legacy initiation failures in reconciliation', function (): void {
    walletCashEnabled();
    Sanctum::actingAs(User::factory()->create());
    $failed = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload())->assertCreated()->json('data.uuid');
    $unknown = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload(['amount' => 6000]))->assertCreated()->json('data.uuid');
    $failedTopUp = WalletTopUp::query()->where('uuid', $failed)->firstOrFail();
    $unknownTopUp = WalletTopUp::query()->where('uuid', $unknown)->firstOrFail();
    $failedTopUp->payment->update([
        'status' => 'failed', 'provider_reference' => null,
        'provider_payload' => ['error' => 'Attempt to read property "uuid" on null'],
    ]);
    $unknownTopUp->payment->update(['provider_reference' => null, 'provider_payload' => ['initiation_phase' => 'uncertain']]);
    $failedTopUp->update(['next_reconciliation_at' => now()]);
    $unknownTopUp->update(['next_reconciliation_at' => now()]);

    $this->artisan('wallet:reconcile-topups --dry-run')->expectsOutput($failed.' close_failed')->assertSuccessful();
    expect($failedTopUp->fresh()->status->value)->toBe('pending');
    $this->artisan('wallet:reconcile-topups')->assertSuccessful();
    expect($failedTopUp->fresh()->status->value)->toBe('failed')
        ->and($unknownTopUp->fresh()->status->value)->toBe('pending');
});

function failingKkiaPayProvider(): PaymentProvider
{
    $provider = Mockery::mock(PaymentProvider::class);
    $provider->shouldReceive('initiate')->andThrow(new RuntimeException('KKiaPay sandbox unavailable'));
    $provider->shouldReceive('verify')->andReturn(new ProviderResponse(PaymentStatus::Pending));

    return $provider;
}

function widgetKkiaPayProvider(): PaymentProvider
{
    $provider = Mockery::mock(PaymentProvider::class);
    $provider->shouldReceive('initiate')->andReturnUsing(fn (Payment $payment): ProviderResponse => new ProviderResponse(
        status: PaymentStatus::Pending,
        payload: ['widget' => ['key' => 'public-key', 'amount' => $payment->amount, 'partnerId' => $payment->uuid]],
    ));
    $provider->shouldReceive('verify')->andReturn(new ProviderResponse(PaymentStatus::Pending));

    return $provider;
}

function bindWalletTopUpProvider(PaymentProvider $kkiapay, string $provider = 'kkiapay'): void
{
    $manager = new PaymentManager;
    (new ReflectionClass($manager))->getProperty('resolved')->setValue($manager, [$provider => $kkiapay]);
    app()->instance(PaymentManager::class, $manager);
}

it('reopens the KKiaPay widget when an uninitiated top-up is replayed', function (): void {
    walletCashEnabled();
    bindWalletTopUpProvider(failingKkiaPayProvider());
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $payload = walletTopUpPayload(['provider' => 'kkiapay']);
    $uuid = $this->postJson('/api/v1/wallet/topups', $payload)
        ->assertCreated()
        ->assertJsonPath('data.payment.next_action', 'wait')
        ->assertJsonPath('data.payment.widget', null)
        ->json('data.uuid');

    $this->postJson('/api/v1/wallet/topups', array_replace($payload, ['amount' => 4900, 'idempotency_key' => (string) Str::uuid()]))
        ->assertCreated();

    bindWalletTopUpProvider(widgetKkiaPayProvider());
    $this->postJson('/api/v1/wallet/topups', array_replace($payload, ['idempotency_key' => (string) Str::uuid()]))
        ->assertOk()
        ->assertJsonPath('data.uuid', $uuid)
        ->assertJsonPath('data.payment.next_action', 'open_widget')
        ->assertJsonPath('data.payment.widget.amount', 5000);

    expect($uuid)->not->toBeEmpty()
        ->and(WalletTopUp::query()->where('uuid', $uuid)->firstOrFail()->payment->provider_payload['initiation_phase'])->toBe('ready');
});

it('retries a KKiaPay initiation during reconciliation instead of waiting forever', function (): void {
    walletCashEnabled();
    bindWalletTopUpProvider(failingKkiaPayProvider());
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $uuid = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload(['provider' => 'kkiapay']))
        ->assertCreated()->assertJsonPath('data.payment.next_action', 'wait')->json('data.uuid');
    $topup = WalletTopUp::query()->where('uuid', $uuid)->firstOrFail();
    $topup->update(['next_reconciliation_at' => now()]);

    bindWalletTopUpProvider(widgetKkiaPayProvider());
    $this->artisan('wallet:reconcile-topups')->assertSuccessful();

    expect($topup->fresh()->status->value)->toBe('pending')
        ->and($topup->payment->fresh()->provider_payload['initiation_phase'])->toBe('ready')
        ->and($topup->payment->fresh()->provider_payload['widget']['amount'])->toBe(5000);
});

it('expires a top-up that never got a payment hand-off so its amount is available again', function (): void {
    walletCashEnabled();
    bindWalletTopUpProvider(failingKkiaPayProvider());
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $payload = walletTopUpPayload(['provider' => 'kkiapay']);
    $uuid = $this->postJson('/api/v1/wallet/topups', $payload)
        ->assertCreated()->assertJsonPath('data.payment.next_action', 'wait')->json('data.uuid');
    $topup = WalletTopUp::query()->where('uuid', $uuid)->firstOrFail();

    $this->travel(16)->minutes();
    $topup->update(['next_reconciliation_at' => now()]);
    $this->artisan('wallet:reconcile-topups')->assertSuccessful();

    expect($topup->fresh()->status->value)->toBe('failed')
        ->and($topup->payment->fresh()->status->value)->toBe('failed')
        ->and($topup->payment->fresh()->failure_code)->toBe('provider_initiation_failed')
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(0);

    bindWalletTopUpProvider(widgetKkiaPayProvider());
    $this->postJson('/api/v1/wallet/topups', array_replace($payload, ['idempotency_key' => (string) Str::uuid()]))
        ->assertCreated();
    $this->travelBack();
});

function unavailableMobileMoneyProvider(string $provider): PaymentProvider
{
    $mock = Mockery::mock(PaymentProvider::class);
    $mock->shouldReceive('initiate')->andReturnUsing(function (Payment $payment) use ($provider): ProviderResponse {
        $payment->update($provider === 'fedapay'
            ? ['provider_transaction_id' => 'TX-'.$payment->uuid, 'provider_reference' => 'FEDA-'.$payment->uuid]
            : ['provider_reference' => $payment->uuid]);

        return new ProviderResponse(
            status: PaymentStatus::Pending,
            providerReference: $payment->provider_reference,
            providerTransactionId: $payment->provider_transaction_id,
            payload: ['initiation_issue' => 'provider_unavailable'],
        );
    });
    $mock->shouldReceive('verify')->andReturn(new ProviderResponse(PaymentStatus::Pending));

    return $mock;
}

it('does not block another amount when the provider initiation failed with a reference', function (): void {
    walletCashEnabled();
    bindWalletTopUpProvider(unavailableMobileMoneyProvider('mtn_momo'), 'mtn_momo');
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $first = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload(['provider' => 'mtn_momo']))
        ->assertCreated()->assertJsonPath('data.payment.next_action', 'verify')->json('data.uuid');

    $this->postJson('/api/v1/wallet/topups', walletTopUpPayload([
        'provider' => 'mtn_momo', 'amount' => 3000, 'idempotency_key' => (string) Str::uuid(),
    ]))->assertCreated();

    expect(WalletTopUp::query()->where('uuid', $first)->firstOrFail()->payment->provider_reference)->not->toBeNull()
        ->and(WalletTopUp::query()->where('status', 'pending')->count())->toBe(2);
});

it('expires a FedaPay or MTN top-up stuck after a failed push', function (string $provider): void {
    walletCashEnabled();
    bindWalletTopUpProvider(unavailableMobileMoneyProvider($provider), $provider);
    $scenario = walletScenario();
    Sanctum::actingAs($scenario['user']);

    $uuid = $this->postJson('/api/v1/wallet/topups', walletTopUpPayload(['provider' => $provider]))
        ->assertCreated()->json('data.uuid');
    $topup = WalletTopUp::query()->where('uuid', $uuid)->firstOrFail();

    $this->travel(16)->minutes();
    $topup->update(['next_reconciliation_at' => now()]);
    $this->artisan('wallet:reconcile-topups')->assertSuccessful();

    expect($topup->fresh()->status->value)->toBe('failed')
        ->and($topup->payment->fresh()->status->value)->toBe('failed')
        ->and($topup->payment->fresh()->failure_code)->toBe('provider_initiation_failed')
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(0);

    $this->postJson('/api/v1/wallet/topups', walletTopUpPayload([
        'provider' => $provider, 'amount' => 3000, 'idempotency_key' => (string) Str::uuid(),
    ]))->assertCreated();
    $this->travelBack();
})->with(['fedapay', 'mtn_momo']);

it('keeps refusing another amount while an initiation is still unaccounted for', function (): void {
    walletCashEnabled();
    bindWalletTopUpProvider(unavailableMobileMoneyProvider('mtn_momo'), 'mtn_momo');
    Sanctum::actingAs(User::factory()->create());
    $payload = walletTopUpPayload(['provider' => 'mtn_momo']);
    $uuid = $this->postJson('/api/v1/wallet/topups', $payload)->assertCreated()->json('data.uuid');

    WalletTopUp::query()->where('uuid', $uuid)->firstOrFail()->payment->update([
        'provider_reference' => null,
        'failure_code' => null,
        'provider_payload' => ['purpose' => 'wallet_topup', 'initiation_phase' => 'starting'],
    ]);

    $other = array_replace($payload, ['amount' => 3000, 'idempotency_key' => (string) Str::uuid()]);
    $this->postJson('/api/v1/wallet/topups', $other)->assertStatus(409);

    $this->travel(16)->minutes();
    $this->postJson('/api/v1/wallet/topups', $other)->assertCreated();
    $this->travelBack();
});
