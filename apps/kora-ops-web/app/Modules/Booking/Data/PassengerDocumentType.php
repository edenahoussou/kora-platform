<?php

namespace App\Modules\Booking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PassengerDocumentType: string
{
    use HasEnumUtilities;

    case NationalId = 'national_id';
    case Passport = 'passport';
    case ConsularCard = 'consular_card';
    case BirthCertificate = 'birth_certificate';
}
