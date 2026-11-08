<?php

namespace App\Console\Commands\Wallet;

use App\Modules\Wallet\Actions\ActivateScheduledWalletLoyaltyRulesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('wallet:loyalty-rules:activate-scheduled')]
#[Description('Active à échéance la version de fidélité BUS programmée.')]
class ActivateScheduledLoyaltyRules extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ActivateScheduledWalletLoyaltyRulesAction $activateScheduled): int
    {
        $count = $activateScheduled->execute();
        $this->info($count > 0 ? 'Version de fidélité activée.' : 'Aucune version à activer.');

        return self::SUCCESS;
    }
}
