<?php

namespace App\Modules\Ticketing\Data;

enum TicketIssuanceStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Processing = 'processing';
    case Issued = 'issued';
    case Failed = 'failed';
}
