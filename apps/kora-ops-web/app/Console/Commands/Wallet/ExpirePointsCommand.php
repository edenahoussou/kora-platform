<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Wallet\Actions\ExpireWalletPointsAction;
use Illuminate\Console\Command;

class ExpirePointsCommand extends Command
{
    protected $signature = 'wallet:expire-points';

    protected $description = 'Expire les points des Wallets sans activité qualifiante';

    public function handle(ExpireWalletPointsAction $expireWalletPoints): int
    {
        $count = $expireWalletPoints->handle();
        $this->info("{$count} Wallet(s) expiré(s).");

        return self::SUCCESS;
    }
}
