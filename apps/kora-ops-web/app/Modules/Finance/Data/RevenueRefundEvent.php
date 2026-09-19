<?php

namespace App\Modules\Finance\Data;

use Carbon\CarbonImmutable;

final readonly class RevenueRefundEvent
{
    public function __construct(
        public string $uuid,
        public ?string $paymentUuid,
        public ?string $reservationReference,
        public ?string $agencyUuid,
        public string $agencyName,
        public string $channel,
        public string $method,
        public ?string $provider,
        public ?string $providerReference,
        public ?string $providerTransactionId,
        public ?string $network,
        public int $amount,
        public int $recognized,
        public int $excess,
        public string $status,
        public string $destination,
        public CarbonImmutable $date,
        public bool $unlinked,
        public bool $ineligiblePayment,
    ) {}
}
