<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Wallet\Actions\GrantAnniversaryBonusesAction;
use Illuminate\Console\Command;

class AnniversaryBonusesCommand extends Command
{
    protected $signature = 'wallet:anniversary-bonuses';

    protected $description = 'Accorde les bonus d’anniversaire de compte';

    public function handle(GrantAnniversaryBonusesAction $grantAnniversaryBonuses): int
    {
        $count = $grantAnniversaryBonuses->handle();
        $this->info("{$count} bonus d’anniversaire accordé(s).");

        return self::SUCCESS;
    }
}
