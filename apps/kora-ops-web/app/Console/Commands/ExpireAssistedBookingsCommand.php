<?php

namespace App\Console\Commands;

use App\Modules\AssistedBooking\Services\AssistedBookingWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bus:expire-assisted-bookings')]
#[Description('Expire or close assisted bookings using the effective commercial cutoff')]
class ExpireAssistedBookingsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AssistedBookingWorkflow $workflow): int
    {
        $count = $workflow->expireDue();
        $this->info("Closed {$count} assisted booking(s).");

        return self::SUCCESS;
    }
}
