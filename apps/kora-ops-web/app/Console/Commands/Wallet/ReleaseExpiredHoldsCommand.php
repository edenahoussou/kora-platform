<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Wallet\Actions\ReleaseExpiredWalletHoldsAction;
use Illuminate\Console\Command;

class ReleaseExpiredHoldsCommand extends Command
{
    protected $signature = 'wallet:release-expired-holds';

    protected $description = 'Libère les holds Wallet expirés';

    public function handle(ReleaseExpiredWalletHoldsAction $releaseExpiredHolds): int
    {
        $count = $releaseExpiredHolds->handle();
        $this->info("{$count} hold(s) libéré(s).");

        return self::SUCCESS;
    }
}
