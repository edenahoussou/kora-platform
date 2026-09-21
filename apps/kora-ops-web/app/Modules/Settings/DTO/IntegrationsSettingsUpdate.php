<?php

namespace App\Modules\Settings\DTO;

use App\Modules\Settings\Data\SettingsGroup;

final readonly class IntegrationsSettingsUpdate implements SettingsUpdate
{
    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $clearSecrets
     */
    public function __construct(
        private array $values,
        private array $clearSecrets = [],
    ) {}

    public function group(): SettingsGroup
    {
        return SettingsGroup::Integrations;
    }

    public function values(): array
    {
        return $this->values;
    }

    public function clearSecrets(): array
    {
        return $this->clearSecrets;
    }
}
