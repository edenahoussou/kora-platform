<?php

namespace App\Modules\Booking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PassengerDocumentType: string
{
    use HasEnumUtilities;

    case NationalId = 'national_id';
    case BiometricCard = 'biometric_card';
    case Passport = 'passport';
    case ConsularCard = 'consular_card';
    case Cip = 'cip';
}
