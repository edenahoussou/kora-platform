<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Wallet\Actions\ReleaseExpiredWalletHoldsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReleaseExpiredWalletHoldsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReleaseExpiredWalletHoldsAction $releaseExpiredHolds): void
    {
        $releaseExpiredHolds->handle();
    }
}
