<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\Models\Payment;
use Illuminate\Http\Request;

interface PaymentProvider
{
    /**
     * Initiate a payment with the external provider.
     *
     * @param  array<string, mixed>  $payload  Customer-provided data (phone, method, etc.)
     */
    public function initiate(Payment $payment, array $payload = []): ProviderResponse;

    /**
     * Query or confirm the payment status from the provider.
     */
    public function confirm(Payment $payment): ProviderResponse;

    /**
     * Verify the authenticity of an incoming webhook request.
     *
     * @return array<string, mixed>|null The normalized payload if valid, null otherwise.
     */
    public function verifyWebhook(Request $request): ?array;

    /**
     * Determine if this provider handles the given method and provider key.
     */
    public function supports(string $method, string $provider): bool;

    /**
     * Get the provider's canonical name (e.g. 'fedapay', 'mtn_momo').
     */
    public function name(): string;
}
