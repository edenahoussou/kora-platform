<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Wallet\Actions\ExpireWalletPointsAction;
use Illuminate\Console\Command;

/**
 * Expire les points des Wallets inactifs.
 */
class ExpirePointsCommand extends Command
{
    protected $signature = 'wallet:expire-points';

    protected $description = 'Expire les points des Wallets sans activité qualifiante';

    /**
     * Exécute l'action d'expiration et affiche le bilan.
     *
     * @param  ExpireWalletPointsAction  $expireWalletPoints  Action d'expiration
     * @return int Code de sortie de la commande
     */
    public function handle(ExpireWalletPointsAction $expireWalletPoints): int
    {
        $count = $expireWalletPoints->handle();
        $this->info("{$count} Wallet(s) expiré(s).");

        return self::SUCCESS;
    }
}
