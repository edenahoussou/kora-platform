<?php

namespace App\Modules\Wallet\Jobs;

use App\Modules\Wallet\Actions\GrantAnniversaryBonusesAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GrantAnniversaryBonusJob implements ShouldQueue
{
    use Queueable;

    public function handle(GrantAnniversaryBonusesAction $grantAnniversaryBonuses): void
    {
        $grantAnniversaryBonuses->handle();
    }
}
