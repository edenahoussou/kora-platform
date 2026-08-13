<?php

namespace App\Modules\IdentityAccess\Data;

enum OtpPurpose: string
{
    case Registration = 'registration';
    case LoginNewDevice = 'login_new_device';
    case PinReset = 'pin_reset';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
