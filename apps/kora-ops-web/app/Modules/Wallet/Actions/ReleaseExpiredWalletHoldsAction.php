<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Models\WalletHold;

/**
 * Libère les holds Wallet dont la durée de vie est dépassée.
 */
final class ReleaseExpiredWalletHoldsAction
{
    public function __construct(private readonly ReleaseWalletHoldAction $releaseHold) {}

    public function handle(): int
    {
        $holds = WalletHold::query()
            ->where('status', WalletHoldStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($holds as $hold) {
            $this->releaseHold->handle($hold);
        }

        return $holds->count();
    }
}
