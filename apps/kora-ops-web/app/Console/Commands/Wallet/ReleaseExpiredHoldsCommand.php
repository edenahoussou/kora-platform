<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Wallet\Actions\ReleaseExpiredWalletHoldsAction;
use Illuminate\Console\Command;

/**
 * Libère les holds Wallet expirés.
 */
class ReleaseExpiredHoldsCommand extends Command
{
    protected $signature = 'wallet:release-expired-holds';

    protected $description = 'Libère les holds Wallet expirés';

    /**
     * Exécute l'action de libération et affiche le bilan.
     *
     * @param  ReleaseExpiredWalletHoldsAction  $releaseExpiredHolds  Action de libération
     * @return int Code de sortie de la commande
     */
    public function handle(ReleaseExpiredWalletHoldsAction $releaseExpiredHolds): int
    {
        $count = $releaseExpiredHolds->handle();
        $this->info("{$count} hold(s) libéré(s).");

        return self::SUCCESS;
    }
}
