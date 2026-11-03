<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Wallet\Actions\ReleaseExpiredWalletHoldsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Libère les holds Wallet expirés.
 */
class ReleaseExpiredWalletHoldsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Exécute l'action de libération.
     *
     * @param  ReleaseExpiredWalletHoldsAction  $releaseExpiredHolds  Action de libération
     */
    public function handle(ReleaseExpiredWalletHoldsAction $releaseExpiredHolds): void
    {
        $releaseExpiredHolds->handle();
    }
}
