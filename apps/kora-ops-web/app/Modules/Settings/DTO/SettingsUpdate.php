<?php

namespace App\Modules\Settings\DTO;

use App\Modules\Settings\Data\SettingsGroup;

interface SettingsUpdate
{
    public function group(): SettingsGroup;

    /** @return array<string, mixed> */
    public function values(): array;

    /** @return list<string> */
    public function clearSecrets(): array;
}
