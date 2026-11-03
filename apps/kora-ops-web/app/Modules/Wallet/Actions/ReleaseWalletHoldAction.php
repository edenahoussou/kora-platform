<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Models\WalletHold;
use Illuminate\Support\Facades\DB;

/**
 * Libère un hold sans écriture économique (échec, expiration, annulation).
 */
final class ReleaseWalletHoldAction
{
    public function handle(WalletHold $hold): WalletHold
    {
        return DB::transaction(function () use ($hold): WalletHold {
            $locked = WalletHold::query()->whereKey($hold->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === WalletHoldStatus::Active) {
                $locked->update(['status' => WalletHoldStatus::Released]);
            }

            return $locked;
        });
    }

    public function releaseForPayment(int $paymentId): void
    {
        WalletHold::query()
            ->where('payment_id', $paymentId)
            ->where('status', WalletHoldStatus::Active->value)
            ->get()
            ->each(fn (WalletHold $hold) => $this->handle($hold));
    }
}
