<?php

namespace App\Console\Commands;

use App\Modules\Booking\Actions\ExpireSeatHoldsAction;
use Illuminate\Console\Command;

class ExpireSeatHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bus:expire-holds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire past-due seat holds and release inventory';

    /**
     * Execute the console command.
     */
    public function handle(ExpireSeatHoldsAction $expireSeatHolds): int
    {
        $count = $expireSeatHolds->handle();
        $this->info("Expired {$count} seat hold(s).");

        return self::SUCCESS;
    }
}
