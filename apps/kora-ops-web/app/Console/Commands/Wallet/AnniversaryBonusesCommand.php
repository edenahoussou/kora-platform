<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Wallet\Actions\GrantAnniversaryBonusesAction;
use Illuminate\Console\Command;

/**
 * Accorde les bonus d'anniversaire de compte.
 */
class AnniversaryBonusesCommand extends Command
{
    protected $signature = 'wallet:anniversary-bonuses';

    protected $description = 'Accorde les bonus d’anniversaire de compte';

    /**
     * Exécute l'action d'anniversaire et affiche le bilan.
     *
     * @param  GrantAnniversaryBonusesAction  $grantAnniversaryBonuses  Action d'anniversaire
     * @return int Code de sortie de la commande
     */
    public function handle(GrantAnniversaryBonusesAction $grantAnniversaryBonuses): int
    {
        $count = $grantAnniversaryBonuses->handle();
        $this->info("{$count} bonus d’anniversaire accordé(s).");

        return self::SUCCESS;
    }
}
