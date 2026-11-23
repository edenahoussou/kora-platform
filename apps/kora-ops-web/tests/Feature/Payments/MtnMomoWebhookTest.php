<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use App\Modules\Payments\Providers\MtnMomoAdapter;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
use App\Modules\Wallet\Data\WalletTopUpStatus;
use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function mtnWebhookSecret(): string
{
    return str_repeat('c', 40);
}

function mtnWebhookCallbackToken(?string $secret = null): string
{
    return substr(hash_hmac('sha256', 'kora:mtn-momo:callback', $secret ?? mtnWebhookSecret()), 0, 32);
}

function mtnWebhookAdapter(): MtnMomoAdapter
{
    return new MtnMomoAdapter(
        subscriptionKey: 'subscription',
        userId: 'user',
        apiKey: 'api-key',
        environment: 'sandbox',
        callbackUrl: 'https://staging.kora.test/api/v1/payments/webhook/mtn-momo',
        webhookSecret: mtnWebhookSecret(),
    );
}

function bindMtnWebhookAdapter(MtnMomoAdapter $adapter): void
{
    $manager = new PaymentManager;
    $reflection = new ReflectionClass($manager);
    $reflection->getProperty('resolved')->setValue($manager, ['mtn_momo' => $adapter]);
    app()->instance(PaymentManager::class, $manager);
}

function fakeMtnVerification(array $payload): void
{
    Http::fake(function (ClientRequest $request) use ($payload) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response($payload, 200);
    });
}

function mtnWebhookUrl(string $token): string
{
    return "/api/v1/payments/webhook/mtn-momo/{$token}";
}

it('confirms a wallet top-up once through the MTN callback and server verification', function (): void {
    walletCashEnabled();
    $scenario = walletScenario();
    bindMtnWebhookAdapter(mtnWebhookAdapter());

    $payment = Payment::create([
        'uuid' => (string) Str::uuid(),
        'method' => PaymentMethod::MobileMoney,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-WALLET-1',
        'provider_amount' => 5000,
        'provider_currency' => 'EUR',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    $topup = WalletTopUp::create([
        'wallet_id' => $scenario['wallet']->id,
        'payment_id' => $payment->id,
        'amount' => 5000,
        'status' => WalletTopUpStatus::Pending,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    fakeMtnVerification([
        'status' => 'SUCCESSFUL',
        'amount' => '5000',
        'currency' => 'EUR',
        'externalId' => $payment->uuid,
        'financialTransactionId' => 'FIN-WALLET-1',
    ]);

    $payload = ['externalId' => $payment->uuid, 'status' => 'SUCCESSFUL', 'financialTransactionId' => 'FIN-WALLET-1'];

    $this->postJson(mtnWebhookUrl(mtnWebhookCallbackToken()), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonPath('payment_uuid', $payment->uuid);

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(5000)
        ->and($topup->fresh()->status)->toBe(WalletTopUpStatus::Confirmed)
        ->and($payment->fresh()->provider_transaction_id)->toBe('FIN-WALLET-1')
        ->and(DB::table('payment_provider_receipts')
            ->where('provider', 'mtn_momo')
            ->where('transaction_reference', 'FIN-WALLET-1')
            ->count())->toBe(1);

    $this->postJson(mtnWebhookUrl(mtnWebhookCallbackToken()), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'confirmed');

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(5000)
        ->and($topup->fresh()->status)->toBe(WalletTopUpStatus::Confirmed)
        ->and(DB::table('payment_provider_receipts')
            ->where('provider', 'mtn_momo')
            ->where('transaction_reference', 'FIN-WALLET-1')
            ->count())->toBe(1);
});

it('does not confirm a payment when the callback lies but server verification is pending', function (): void {
    walletCashEnabled();
    $scenario = walletScenario();
    bindMtnWebhookAdapter(mtnWebhookAdapter());

    $payment = Payment::create([
        'uuid' => (string) Str::uuid(),
        'method' => PaymentMethod::MobileMoney,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-WALLET-2',
        'provider_amount' => 5000,
        'provider_currency' => 'EUR',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    WalletTopUp::create([
        'wallet_id' => $scenario['wallet']->id,
        'payment_id' => $payment->id,
        'amount' => 5000,
        'status' => WalletTopUpStatus::Pending,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    fakeMtnVerification([
        'status' => 'PENDING',
        'amount' => '5000',
        'currency' => 'EUR',
        'externalId' => $payment->uuid,
    ]);

    $this->postJson(mtnWebhookUrl(mtnWebhookCallbackToken()), [
        'externalId' => $payment->uuid,
        'status' => 'SUCCESSFUL',
    ])->assertOk()->assertJsonPath('status', 'pending');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($scenario['wallet']->fresh()->cash_balance)->toBe(0);
});

it('resolves a payment from the MTN referenceId when externalId is absent', function (): void {
    $user = User::factory()->create();

    $reservation = Reservation::create([
        'created_by_user_id' => $user->id,
        'status' => ReservationStatus::Held,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'mobile',
        'expires_at' => now()->addMinutes(15),
    ]);

    $payment = Payment::create([
        'reservation_id' => $reservation->id,
        'method' => PaymentMethod::MobileMoney,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-REF-1',
        'provider_amount' => 15000,
        'provider_currency' => 'EUR',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    bindMtnWebhookAdapter(mtnWebhookAdapter());
    fakeMtnVerification([
        'status' => 'SUCCESSFUL',
        'amount' => '15000',
        'currency' => 'EUR',
        'externalId' => $payment->uuid,
        'financialTransactionId' => 'FIN-REF-1',
    ]);

    $this->postJson(mtnWebhookUrl(mtnWebhookCallbackToken()), [
        'referenceId' => 'MM-REF-1',
        'status' => 'SUCCESSFUL',
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Paid);
});

it('does not persist a financialTransactionId from an inconsistent MTN response', function (): void {
    bindMtnWebhookAdapter(mtnWebhookAdapter());

    $reservation = Reservation::create([
        'created_by_user_id' => User::factory()->create()->id,
        'status' => ReservationStatus::Held,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'mobile',
        'expires_at' => now()->addMinutes(15),
    ]);
    $payment = Payment::create([
        'reservation_id' => $reservation->id,
        'method' => PaymentMethod::MobileMoney,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-MISMATCH-1',
        'provider_amount' => 15000,
        'provider_currency' => 'EUR',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    fakeMtnVerification([
        'status' => 'SUCCESSFUL',
        'amount' => '15000',
        'currency' => 'EUR',
        'externalId' => (string) Str::uuid(),
        'financialTransactionId' => 'FIN-MISMATCH',
    ]);

    $this->postJson(mtnWebhookUrl(mtnWebhookCallbackToken()), [
        'externalId' => $payment->uuid,
        'status' => 'SUCCESSFUL',
        'financialTransactionId' => 'FIN-MISMATCH',
    ])->assertOk()->assertJsonPath('status', 'pending');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($payment->fresh()->provider_transaction_id)->toBeNull();
});

it('confirms a bus payment once and never dispatches a second ticket job on duplicate callback', function (): void {
    Queue::fake([IssueReservationTicketsJob::class]);
    bindMtnWebhookAdapter(mtnWebhookAdapter());

    $user = User::factory()->create();

    $reservation = Reservation::create([
        'created_by_user_id' => $user->id,
        'status' => ReservationStatus::Held,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'mobile',
        'expires_at' => now()->addMinutes(15),
    ]);

    $payment = Payment::create([
        'reservation_id' => $reservation->id,
        'method' => PaymentMethod::MobileMoney,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-BUS-1',
        'provider_amount' => 15000,
        'provider_currency' => 'EUR',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    fakeMtnVerification([
        'status' => 'SUCCESSFUL',
        'amount' => '15000',
        'currency' => 'EUR',
        'externalId' => $payment->uuid,
        'financialTransactionId' => 'FIN-BUS-1',
    ]);

    $payload = ['externalId' => $payment->uuid, 'status' => 'SUCCESSFUL'];

    $this->postJson(mtnWebhookUrl(mtnWebhookCallbackToken()), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'confirmed');

    $this->postJson(mtnWebhookUrl(mtnWebhookCallbackToken()), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'confirmed');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Paid)
        ->and($payment->fresh()->provider_transaction_id)->toBe('FIN-BUS-1');

    Queue::assertPushed(IssueReservationTicketsJob::class, 1);
});

it('maps an MTN cancellation to cancelled and expires the reservation', function (): void {
    bindMtnWebhookAdapter(mtnWebhookAdapter());

    $user = User::factory()->create();

    $reservation = Reservation::create([
        'created_by_user_id' => $user->id,
        'status' => ReservationStatus::Held,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'mobile',
        'expires_at' => now()->addMinutes(15),
    ]);

    $payment = Payment::create([
        'reservation_id' => $reservation->id,
        'method' => PaymentMethod::MobileMoney,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-BUS-2',
        'provider_amount' => 15000,
        'provider_currency' => 'EUR',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    fakeMtnVerification([
        'status' => 'CANCELLED',
        'externalId' => $payment->uuid,
    ]);

    $this->postJson(mtnWebhookUrl(mtnWebhookCallbackToken()), [
        'externalId' => $payment->uuid,
        'status' => 'CANCELLED',
    ])->assertOk()->assertJsonPath('status', 'cancelled');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Expired);
});

it('accepts MTN callbacks over %s with the same signature checks', function (string $httpMethod): void {
    bindMtnWebhookAdapter(mtnWebhookAdapter());

    $user = User::factory()->create();
    $reservation = Reservation::create([
        'created_by_user_id' => $user->id,
        'status' => ReservationStatus::Held,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'mobile',
        'expires_at' => now()->addMinutes(15),
    ]);
    $payment = Payment::create([
        'reservation_id' => $reservation->id,
        'method' => PaymentMethod::MobileMoney,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-'.strtoupper($httpMethod).'-1',
        'provider_amount' => 15000,
        'provider_currency' => 'EUR',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    fakeMtnVerification([
        'status' => 'SUCCESSFUL',
        'amount' => '15000',
        'currency' => 'EUR',
        'externalId' => $payment->uuid,
        'financialTransactionId' => 'FIN-'.strtoupper($httpMethod),
    ]);

    $method = $httpMethod.'Json';
    $this->{$method}(mtnWebhookUrl(mtnWebhookCallbackToken()), [
        'externalId' => $payment->uuid,
        'status' => 'SUCCESSFUL',
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    $this->{$method}(mtnWebhookUrl('bad-token'), [
        'externalId' => $payment->uuid,
        'status' => 'SUCCESSFUL',
    ])->assertStatus(403);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Paid);
})->with([
    'POST' => ['post'],
    'PUT' => ['put'],
]);

it('rejects an invalid MTN callback token before any provider call', function (): void {
    bindMtnWebhookAdapter(mtnWebhookAdapter());
    Http::fake();

    $this->postJson(mtnWebhookUrl('bad-token'), [
        'externalId' => (string) Str::uuid(),
        'status' => 'SUCCESSFUL',
    ])->assertStatus(403);

    Http::assertNothingSent();
});

it('returns 400 when the MTN callback has no correlation identifier', function (): void {
    bindMtnWebhookAdapter(mtnWebhookAdapter());
    Http::fake();

    $this->postJson(mtnWebhookUrl(mtnWebhookCallbackToken()), [
        'status' => 'SUCCESSFUL',
    ])->assertStatus(400);

    Http::assertNothingSent();
});

it('returns 404 for an unknown MTN payment reference', function (): void {
    bindMtnWebhookAdapter(mtnWebhookAdapter());
    Http::fake();

    $this->postJson(mtnWebhookUrl(mtnWebhookCallbackToken()), [
        'externalId' => (string) Str::uuid(),
        'status' => 'SUCCESSFUL',
    ])->assertNotFound();

    Http::assertNothingSent();
});
