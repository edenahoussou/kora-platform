<?php

namespace App\Modules\Wallet\Data;

class WalletOpsMovementData
{
    /**
     * @param  array{uuid: string, name: string|null, phone: string|null}  $holder
     * @param  array{type: string, label: string, uuid: string, url: string|null}|null  $source
     * @param  array{uuid: string, name: string}|null  $agency
     * @param  array{uuid: string, version: int}|null  $loyaltyRuleVersion
     */
    public function __construct(
        public string $uuid,
        public string $walletUuid,
        public array $holder,
        public string $balanceType,
        public string $direction,
        public int $amount,
        public string $reason,
        public string $reasonLabel,
        public ?array $source,
        public ?array $agency,
        public ?array $loyaltyRuleVersion,
        public ?string $actorName,
        public string $createdAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'walletUuid' => $this->walletUuid,
            'holder' => $this->holder,
            'balanceType' => $this->balanceType,
            'direction' => $this->direction,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'reasonLabel' => $this->reasonLabel,
            'source' => $this->source,
            'agency' => $this->agency,
            'loyaltyRuleVersion' => $this->loyaltyRuleVersion,
            'actorName' => $this->actorName,
            'createdAt' => $this->createdAt,
        ];
    }
}
