<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Types de service auxquels un taux fiscal peut s'appliquer (CDC §9.8 :
 * "billets de transport, colis, frais et autres services").
 */
enum TaxServiceType: string
{
    use HasEnumUtilities;

    case Transport = "transport";
    case Parcel = "parcel";
    case Fee = "fee";
    case Other = "other";
}
