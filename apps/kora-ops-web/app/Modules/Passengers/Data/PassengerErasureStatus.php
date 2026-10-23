<?php

namespace App\Modules\Passengers\Data;

enum PassengerErasureStatus: string
{
    case Requested = 'requested';
    case Exported = 'exported';
    case Executed = 'executed';
}
