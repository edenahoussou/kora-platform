<?php

namespace App\Modules\Booking\Data;

enum PurchaseType: string
{
    case Individual = 'individual';
    case Group = 'group';
}
