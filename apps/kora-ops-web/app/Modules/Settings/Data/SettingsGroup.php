<?php

namespace App\Modules\Settings\Data;

use App\Modules\Settings\Settings\GeneralSettings;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Modules\Settings\Settings\OperationsSettings;
use Spatie\LaravelSettings\Settings;

enum SettingsGroup: string
{
    case General = 'general';
    case Operations = 'operations';
    case Integrations = 'integrations';

    /** @return class-string<Settings> */
    public function settingsClass(): string
    {
        return match ($this) {
            self::General => GeneralSettings::class,
            self::Operations => OperationsSettings::class,
            self::Integrations => IntegrationsSettings::class,
        };
    }

    public function viewPermission(): string
    {
        return "settings.{$this->value}.view";
    }

    public function managePermission(): string
    {
        return "settings.{$this->value}.manage";
    }
}
