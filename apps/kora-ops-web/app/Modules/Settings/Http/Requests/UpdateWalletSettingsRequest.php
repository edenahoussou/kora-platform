<?php

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Data\SettingsGroup;

final class UpdateWalletSettingsRequest extends SettingsUpdateRequest
{
    /**
     * Groupe de paramètres validé par cette requête.
     *
     * @return SettingsGroup Groupe Wallet
     */
    protected function settingsGroup(): SettingsGroup
    {
        return SettingsGroup::Wallet;
    }
}
