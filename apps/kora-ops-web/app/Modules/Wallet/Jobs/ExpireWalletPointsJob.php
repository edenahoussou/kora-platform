<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Wallet\Actions\ExpireWalletPointsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Expire les points des Wallets inactifs.
 */
class ExpireWalletPointsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Exécute l'action d'expiration.
     *
     * @param  ExpireWalletPointsAction  $expireWalletPoints  Action d'expiration
     */
    public function handle(ExpireWalletPointsAction $expireWalletPoints): void
    {
        $expireWalletPoints->handle();
    }
}
