<?php

namespace App\Modules\Booking\Data;

enum PayerType: string
{
    case Personal = 'personal';
    case Company = 'company';
}
