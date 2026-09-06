<?php

namespace App\Modules\Payments\Data;

enum PaymentRefundStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
