<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Wallet\Actions\GrantAnniversaryBonusesAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Accorde les bonus d'anniversaire de compte.
 */
class GrantAnniversaryBonusJob implements ShouldQueue
{
    use Queueable;

    /**
     * Exécute l'action d'anniversaire.
     *
     * @param  GrantAnniversaryBonusesAction  $grantAnniversaryBonuses  Action d'anniversaire
     */
    public function handle(GrantAnniversaryBonusesAction $grantAnniversaryBonuses): void
    {
        $grantAnniversaryBonuses->handle();
    }
}
