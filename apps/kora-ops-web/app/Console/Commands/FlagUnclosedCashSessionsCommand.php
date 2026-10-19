<?php

namespace App\Console\Commands;

use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Notifications\Services\OpsNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cash:flag-unclosed-sessions')]
#[Description('Alerte les responsables des sessions de caisse restées ouvertes après leur journée')]
class FlagUnclosedCashSessionsCommand extends Command
{
    public function handle(OpsNotifier $notifier): int
    {
        CashSession::query()
            ->where('status', CashSessionStatus::Open->value)
            ->whereDate('business_date', '<', now()->toDateString())
            ->with(['agency', 'agent'])
            ->chunkById(100, fn ($sessions) => $sessions->each(
                fn (CashSession $session) => $notifier->cashSessionLeftOpen($session),
            ));

        return self::SUCCESS;
    }
}
