<?php

use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Providers\MtnMomoAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function mtnMomoPayment(array $overrides = []): Payment
{
    return Payment::create(array_merge([
        'provider' => 'mtn_momo',
        'provider_amount' => 5000,
        'provider_currency' => 'EUR',
        'amount' => 5000,
        'currency' => 'XOF',
        'idempotency_key' => (string) Str::uuid(),
        'status' => PaymentStatus::Pending,
    ], $overrides));
}

function mtnMomoAdapter(array $overrides = []): MtnMomoAdapter
{
    return new MtnMomoAdapter(
        subscriptionKey: $overrides['subscriptionKey'] ?? 'subscription',
        userId: $overrides['userId'] ?? 'user',
        apiKey: $overrides['apiKey'] ?? 'api-key',
        environment: $overrides['environment'] ?? 'sandbox',
        callbackUrl: $overrides['callbackUrl'] ?? 'https://staging.kora.test/api/v1/payments/webhook/mtn-momo',
        webhookSecret: $overrides['webhookSecret'] ?? str_repeat('a', 40),
        targetEnvironment: $overrides['targetEnvironment'] ?? 'mtnbenin',
        baseUrl: $overrides['baseUrl'] ?? null,
    );
}

it('persists the MTN reference before the HTTP call and reuses it on retry', function (): void {
    $payment = mtnMomoPayment();
    $seenReferences = [];

    Http::fake(function (ClientRequest $request) use ($payment, &$seenReferences) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        $seenReferences[] = $request->header('X-Reference-Id')[0] ?? null;

        expect(Payment::query()->findOrFail($payment->id)->provider_reference)
            ->toBe($seenReferences[array_key_last($seenReferences)]);

        return Http::response([], 202);
    });

    $adapter = mtnMomoAdapter();
    $first = $adapter->initiate($payment, ['phone' => '+22997000000']);
    $second = $adapter->initiate($payment->fresh(), ['phone' => '+22997000000']);

    expect($first->status)->toBe(PaymentStatus::Pending)
        ->and($second->status)->toBe(PaymentStatus::Pending)
        ->and($seenReferences)->toHaveCount(2)
        ->and($seenReferences[0])->not->toBeNull()
        ->and($seenReferences[1])->toBe($seenReferences[0])
        ->and($payment->fresh()->provider_reference)->toBe($seenReferences[0]);
});

it('keeps an ambiguous initiation pending instead of failing it', function (int $status): void {
    $payment = mtnMomoPayment();

    Http::fake(function (ClientRequest $request) use ($status) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response([], $status);
    });

    $response = mtnMomoAdapter()->initiate($payment, ['phone' => '+22997000000']);

    expect($response->status)->toBe(PaymentStatus::Pending)
        ->and($response->providerReference)->not->toBeNull()
        ->and($response->payload['initiation_issue'] ?? null)->toBe('provider_unavailable')
        ->and($payment->fresh()->failure_code)->toBeNull();
})->with([500, 502, 408, 429]);

it('treats a definitive provider rejection as failed', function (): void {
    $payment = mtnMomoPayment();

    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response([], 400);
    });

    $response = mtnMomoAdapter()->initiate($payment, ['phone' => '+22997000000']);

    expect($response->status)->toBe(PaymentStatus::Failed)
        ->and($response->isFailed())->toBeTrue()
        ->and($response->providerReference)->not->toBeNull();
});

it('re-verifies a 409 reference conflict instead of creating a second reference', function (): void {
    $payment = mtnMomoPayment();

    Http::fakeSequence()
        ->push(['access_token' => 'token-1'])
        ->push([], 409)
        ->push([
            'status' => 'SUCCESSFUL',
            'amount' => '5000',
            'currency' => 'EUR',
            'externalId' => $payment->uuid,
            'financialTransactionId' => 'FIN-409',
        ], 200);

    $response = mtnMomoAdapter()->initiate($payment, ['phone' => '+22997000000']);

    expect($response->status)->toBe(PaymentStatus::Confirmed)
        ->and($response->providerTransactionId)->toBe('FIN-409')
        ->and($response->providerReference)->toBe($payment->fresh()->provider_reference)
        ->and($response->payload['initiation_issue'] ?? null)->toBe('reference_conflict');
});

it('keeps a timed out initiation pending with the persisted reference', function (): void {
    $payment = mtnMomoPayment();

    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        throw new ConnectionException('connection timed out');
    });

    $response = mtnMomoAdapter()->initiate($payment, ['phone' => '+22997000000']);

    expect($response->status)->toBe(PaymentStatus::Pending)
        ->and($response->providerReference)->toBe($payment->fresh()->provider_reference)
        ->and($response->payload['initiation_issue'] ?? null)->toBe('provider_unavailable');
});

it('refreshes the access token once after a 401', function (): void {
    $payment = mtnMomoPayment();

    Http::fakeSequence()
        ->push(['access_token' => 'token-old'])
        ->push([], 401)
        ->push(['access_token' => 'token-new'])
        ->push([], 202);

    $response = mtnMomoAdapter()->initiate($payment, ['phone' => '+22997000000']);

    expect($response->status)->toBe(PaymentStatus::Pending)
        ->and($response->providerReference)->toBe($payment->fresh()->provider_reference);
});

it('maps MTN statuses and requires a safer payload before terminal states', function (array $payload, PaymentStatus $expected): void {
    $payment = mtnMomoPayment(['provider_reference' => 'ref-123']);
    $payload['externalId'] = $payload['externalId'] === 'PAYMENT_UUID' ? $payment->uuid : $payload['externalId'];

    Http::fake(function (ClientRequest $request) use ($payload) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response($payload, 200);
    });

    $response = mtnMomoAdapter()->verify($payment);

    expect($response->status)->toBe($expected);
})->with([
    'successful' => [[
        'status' => 'SUCCESSFUL',
        'amount' => '5000',
        'currency' => 'EUR',
        'externalId' => 'PAYMENT_UUID',
        'financialTransactionId' => 'FIN-1',
    ], PaymentStatus::Confirmed],
    'failed' => [[
        'status' => 'FAILED',
        'externalId' => 'PAYMENT_UUID',
    ], PaymentStatus::Failed],
    'rejected' => [[
        'status' => 'REJECTED',
        'externalId' => 'PAYMENT_UUID',
    ], PaymentStatus::Failed],
    'timeout' => [[
        'status' => 'TIMEOUT',
        'externalId' => 'PAYMENT_UUID',
    ], PaymentStatus::Failed],
    'cancelled' => [[
        'status' => 'CANCELLED',
        'externalId' => 'PAYMENT_UUID',
    ], PaymentStatus::Cancelled],
    'pending' => [[
        'status' => 'PENDING',
        'externalId' => 'PAYMENT_UUID',
    ], PaymentStatus::Pending],
    'unknown' => [[
        'status' => 'PROCESSING',
        'externalId' => 'PAYMENT_UUID',
    ], PaymentStatus::Pending],
]);

it('maps financialTransactionId to providerTransactionId', function (): void {
    $payment = mtnMomoPayment(['provider_reference' => 'ref-123']);

    Http::fake(function (ClientRequest $request) use ($payment) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response([
            'status' => 'SUCCESSFUL',
            'amount' => '5000',
            'currency' => 'EUR',
            'externalId' => $payment->uuid,
            'financialTransactionId' => 'FIN-123',
        ]);
    });

    $response = mtnMomoAdapter()->verify($payment);

    expect($response->status)->toBe(PaymentStatus::Confirmed)
        ->and($response->providerTransactionId)->toBe('FIN-123');
});

it('fails closed on external id, amount or currency mismatch', function (array $payload, string $issue): void {
    $payment = mtnMomoPayment(['provider_reference' => 'ref-123']);
    $payload['externalId'] = $payload['externalId'] === 'PAYMENT_UUID' ? $payment->uuid : $payload['externalId'];

    Http::fake(function (ClientRequest $request) use ($payload) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response($payload, 200);
    });

    $response = mtnMomoAdapter()->verify($payment);

    expect($response->status)->toBe(PaymentStatus::Pending)
        ->and($response->payload['verification_issue'] ?? null)->toBe($issue);
})->with([
    'external id' => [[
        'status' => 'SUCCESSFUL',
        'amount' => '5000',
        'currency' => 'EUR',
        'externalId' => 'another-payment',
    ], 'external_id_mismatch'],
    'amount' => [[
        'status' => 'SUCCESSFUL',
        'amount' => '4999',
        'currency' => 'EUR',
        'externalId' => 'PAYMENT_UUID',
    ], 'amount_mismatch'],
    'currency' => [[
        'status' => 'SUCCESSFUL',
        'amount' => '5000',
        'currency' => 'XOF',
        'externalId' => 'PAYMENT_UUID',
    ], 'currency_mismatch'],
]);

it('keeps a verification pending when the provider is unavailable', function (): void {
    $payment = mtnMomoPayment(['provider_reference' => 'ref-123']);

    Http::fake(function (ClientRequest $request) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response([], 503);
    });

    $response = mtnMomoAdapter()->verify($payment);

    expect($response->status)->toBe(PaymentStatus::Pending)
        ->and($response->providerReference)->toBe('ref-123');
});

it('builds a callback URL with a derived opaque token and rejects a bad token', function (): void {
    $payment = mtnMomoPayment();
    $secret = str_repeat('b', 40);
    $adapter = mtnMomoAdapter([
        'callbackUrl' => 'https://staging.kora.test/api/v1/payments/webhook/mtn-momo',
        'webhookSecret' => $secret,
    ]);

    Http::fake(function (ClientRequest $request) use (&$callbackUrl) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        $callbackUrl = $request->header('X-Callback-Url')[0] ?? null;

        return Http::response([], 202);
    });

    $adapter->initiate($payment, ['phone' => '+22997000000']);

    $expectedToken = substr(hash_hmac('sha256', 'kora:mtn-momo:callback', $secret), 0, 32);

    expect($callbackUrl)->toBe("https://staging.kora.test/api/v1/payments/webhook/mtn-momo/{$expectedToken}");
    expect(strlen($expectedToken))->toBe(32);

    $valid = Request::create('/api/v1/payments/webhook/mtn-momo', 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_KORA_WEBHOOK_TOKEN' => $expectedToken,
    ], content: json_encode(['externalId' => $payment->uuid, 'status' => 'SUCCESSFUL']));

    $invalid = Request::create('/api/v1/payments/webhook/mtn-momo', 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_KORA_WEBHOOK_TOKEN' => 'wrong',
    ], content: json_encode(['externalId' => $payment->uuid, 'status' => 'SUCCESSFUL']));

    $queryOnly = Request::create('/api/v1/payments/webhook/mtn-momo?token='.$expectedToken, 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
    ], content: json_encode(['externalId' => $payment->uuid, 'status' => 'SUCCESSFUL']));

    expect($adapter->verifyWebhook($valid))->toMatchArray(['externalId' => $payment->uuid])
        ->and($adapter->verifyWebhook($invalid))->toBeNull()
        ->and($adapter->verifyWebhook($queryOnly))->toBeNull();
});

it('rejects a callback when no secret is configured', function (): void {
    $adapter = mtnMomoAdapter(['webhookSecret' => '']);
    $request = Request::create('/api/v1/payments/webhook/mtn-momo', 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_KORA_WEBHOOK_TOKEN' => 'x',
    ], content: json_encode(['externalId' => (string) Str::uuid(), 'status' => 'SUCCESSFUL']));

    expect($adapter->verifyWebhook($request))->toBeNull();
});

it('supports a configurable base url and sends the target environment on token requests', function (): void {
    $payment = mtnMomoPayment();
    $adapter = mtnMomoAdapter([
        'baseUrl' => 'https://momoapi.momo.africa/',
        'targetEnvironment' => 'mtnghana',
    ]);

    $seen = [];

    Http::fake(function (ClientRequest $request) use (&$seen) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            $seen['url'] = $request->url();
            $seen['target'] = $request->header('X-Target-Environment')[0] ?? null;

            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response([], 202);
    });

    $adapter->initiate($payment, ['phone' => '+233240000000']);

    expect($seen['url'])->toBe('https://momoapi.momo.africa/collection/token/')
        ->and($seen['target'])->toBe('mtnghana');
});

it('accepts a decimal string amount while still rejecting a real mismatch', function (): void {
    $payment = mtnMomoPayment(['provider_reference' => 'ref-decimal']);

    Http::fake(function (ClientRequest $request) use ($payment) {
        if (str_ends_with($request->url(), '/collection/token/')) {
            return Http::response(['access_token' => 'token-1']);
        }

        return Http::response([
            'status' => 'SUCCESSFUL',
            'amount' => '5000.00',
            'currency' => 'EUR',
            'externalId' => $payment->uuid,
            'financialTransactionId' => 'FIN-DECIMAL',
        ]);
    });

    $response = mtnMomoAdapter()->verify($payment);

    expect($response->status)->toBe(PaymentStatus::Confirmed)
        ->and($response->providerTransactionId)->toBe('FIN-DECIMAL');
});
