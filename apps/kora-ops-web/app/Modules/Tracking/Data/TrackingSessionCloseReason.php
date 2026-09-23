<?php

namespace App\Modules\Tracking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum TrackingSessionCloseReason: string
{
    use HasEnumUtilities;

    case Arrived = 'arrived';
    case Cancelled = 'cancelled';
    case TerminalLost = 'terminal_lost';
    case TerminalReassigned = 'terminal_reassigned';
    case Superseded = 'superseded';
    case ManualClose = 'manual_close';
}
