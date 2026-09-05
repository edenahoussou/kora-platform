<?php

namespace App\Modules\Booking\Data;

use App\Modules\CompanyAccounts\Models\CompanyAccount;

final class PayerContext
{
    public function __construct(
        public readonly PayerType $type,
        public readonly ?int $payerUserId = null,
        public readonly ?CompanyAccount $companyAccount = null,
        /** @var array<string, mixed> */
        public readonly array $snapshot = [],
    ) {}

    /** @param array<string, mixed> $snapshot */
    public static function personal(?int $userId, array $snapshot = []): self
    {
        return new self(PayerType::Personal, $userId, null, $snapshot);
    }

    /** @param array<string, mixed> $snapshot */
    public static function company(CompanyAccount $companyAccount, array $snapshot = []): self
    {
        return new self(PayerType::Company, null, $companyAccount, $snapshot);
    }
}
