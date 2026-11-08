<?php

namespace App\Modules\Wallet\Data;

class WalletOpsAccountData
{
    /**
     * @param  array{uuid: string, name: string|null, phone: string|null, status: string}  $holder
     * @param  array{cash: int, points: int, pointsValue: int}  $balances
     * @param  array{uuid: string, name: string}|null  $agency
     * @param  list<array<string, mixed>>  $recentMovements
     */
    public function __construct(
        public string $uuid,
        public array $holder,
        public array $balances,
        public ?string $pointsExpiresAt,
        public int $pointsCompensationDue,
        public ?string $lastActivityAt,
        public ?array $agency,
        public string $createdAt,
        public array $recentMovements = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'holder' => $this->holder,
            'balances' => $this->balances,
            'pointsExpiresAt' => $this->pointsExpiresAt,
            'pointsCompensationDue' => $this->pointsCompensationDue,
            'lastActivityAt' => $this->lastActivityAt,
            'agency' => $this->agency,
            'createdAt' => $this->createdAt,
            'recentMovements' => $this->recentMovements,
        ];
    }
}
