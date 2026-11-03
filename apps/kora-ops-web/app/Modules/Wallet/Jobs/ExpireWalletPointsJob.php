<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Wallet\Actions\ExpireWalletPointsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireWalletPointsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ExpireWalletPointsAction $expireWalletPoints): void
    {
        $expireWalletPoints->handle();
    }
}
