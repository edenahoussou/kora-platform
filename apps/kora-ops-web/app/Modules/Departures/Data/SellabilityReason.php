<?php

namespace App\Modules\Departures\Data;

enum SellabilityReason: string
{
    case BeforeCutoff = 'before_cutoff';
    case WithinGrace = 'within_grace';
    case PastCutoff = 'past_cutoff';
    case Boarding = 'boarding';
    case Departed = 'departed';
    case Arrived = 'arrived';
    case Cancelled = 'cancelled';
}
