<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\Data\PaymentStatus;

/**
 * Normalized response from a payment provider.
 *
 * @property-read PaymentStatus $status   KORA-normalized status.
 * @property-read string|null   $providerReference  Provider transaction reference.
 * @property-read string|null   $providerTransactionId  Provider internal transaction ID.
 * @property-read array<string, mixed>  $payload  Raw provider payload for audit.
 * @property-read string|null   $message  Human-readable detail.
 */
final readonly class ProviderResponse
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public PaymentStatus $status,
        public ?string $providerReference = null,
        public array $payload = [],
        public ?string $message = null,
        public ?string $providerTransactionId = null,
    ) {}

    public function isConfirmed(): bool
    {
        return $this->status === PaymentStatus::Confirmed;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::Failed;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}
