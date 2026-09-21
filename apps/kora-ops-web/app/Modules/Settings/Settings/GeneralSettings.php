<?php

namespace App\Modules\Settings\Settings;

use Spatie\LaravelSettings\Settings;

final class GeneralSettings extends Settings
{
    public string $displayName = 'KORA BUS';

    public string $locale = 'fr';

    public string $fallbackLocale = 'fr';

    public string $timezone = 'Africa/Porto-Novo';

    public string $currency = 'XOF';

    public ?string $supportCallPhone = null;

    public ?string $supportWhatsappPhone = null;

    public static function group(): string
    {
        return 'general';
    }
}
