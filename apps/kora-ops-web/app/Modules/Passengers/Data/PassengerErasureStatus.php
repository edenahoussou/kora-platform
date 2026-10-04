<?php

namespace App\Modules\Passengers\Data;

enum PassengerErasureStatus: string
{
    case Exported = 'exported';
    case Executed = 'executed';
}
