<?php

namespace App\Modules\Notifications\Observers;

use App\Modules\Notifications\Services\PassengerNotificationPublisher;
use App\Modules\Payments\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;
use Throwable;

class PaymentRefundNotificationObserver
{
    public function __construct(private PassengerNotificationPublisher $notifications) {}

    public function created(PaymentRefund $refund): void
    {
        $this->publishAfterCommit($refund);
    }

    public function updated(PaymentRefund $refund): void
    {
        if ($refund->wasChanged('status')) {
            $this->publishAfterCommit($refund);
        }
    }

    private function publishAfterCommit(PaymentRefund $refund): void
    {
        DB::afterCommit(function () use ($refund): void {
            try {
                $this->notifications->refundRecorded($refund);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
