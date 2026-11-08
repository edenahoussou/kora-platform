<?php

namespace App\Modules\Wallet\Data;

class WalletOpsDashboardData
{
    /**
     * @param  list<array<string, mixed>>  $recentMovements
     */
    public function __construct(
        public int $accountCount,
        public int $cashTotal,
        public int $pointsTotal,
        public int $pointsValue,
        public int $expiringAccountCount,
        public int $expiringPoints,
        public int $expiringValue,
        public int $pendingTopUpCount,
        public int $pendingTopUpAmount,
        public int $pendingRestitutionCount,
        public int $pendingRestitutionAmount,
        public array $recentMovements,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'accountCount' => $this->accountCount,
            'cashTotal' => $this->cashTotal,
            'pointsTotal' => $this->pointsTotal,
            'pointsValue' => $this->pointsValue,
            'expiringAccountCount' => $this->expiringAccountCount,
            'expiringPoints' => $this->expiringPoints,
            'expiringValue' => $this->expiringValue,
            'pendingTopUpCount' => $this->pendingTopUpCount,
            'pendingTopUpAmount' => $this->pendingTopUpAmount,
            'pendingRestitutionCount' => $this->pendingRestitutionCount,
            'pendingRestitutionAmount' => $this->pendingRestitutionAmount,
            'recentMovements' => $this->recentMovements,
        ];
    }
}
