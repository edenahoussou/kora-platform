<?php

namespace App\Modules\IdentityAccess\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum TerminalActivityType: string
{
    use HasEnumUtilities;

    case Handshake = 'handshake';
    case Sync = 'sync';
    case Scan = 'scan';
    case Print = 'print';
    case Error = 'error';
}
