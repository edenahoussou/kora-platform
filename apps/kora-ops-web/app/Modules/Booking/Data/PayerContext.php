<?php

namespace App\Modules\Booking\Data;

use App\Modules\CompanyAccounts\Models\CompanyAccount;

final class PayerContext
{
    public function __construct(
        public readonly PayerType $type,
        public readonly ?int $payerUserId = null,
        public readonly ?CompanyAccount $companyAccount = null,
    ) {}

    public static function personal(?int $userId): self
    {
        return new self(PayerType::Personal, $userId);
    }

    public static function company(CompanyAccount $companyAccount): self
    {
        return new self(PayerType::Company, null, $companyAccount);
    }
}
