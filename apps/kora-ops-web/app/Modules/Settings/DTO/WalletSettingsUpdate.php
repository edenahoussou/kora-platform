<?php

namespace App\Modules\Settings\DTO;

use App\Modules\Settings\Data\SettingsGroup;

final readonly class WalletSettingsUpdate implements SettingsUpdate
{
    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $clearSecrets
     */
    public function __construct(
        private array $values,
        private array $clearSecrets = [],
    ) {}

    /**
     * Groupe visé par la mutation.
     *
     * @return SettingsGroup Groupe Wallet
     */
    public function group(): SettingsGroup
    {
        return SettingsGroup::Wallet;
    }

    /**
     * Valeurs soumises à persister.
     *
     * @return array<string, mixed> Valeurs du formulaire
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Secrets à effacer explicitement.
     *
     * @return list<string> Propriétés sensibles à vider
     */
    public function clearSecrets(): array
    {
        return $this->clearSecrets;
    }
}
