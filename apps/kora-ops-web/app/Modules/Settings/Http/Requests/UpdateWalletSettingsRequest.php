<?php

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Data\SettingsGroup;

final class UpdateWalletSettingsRequest extends SettingsUpdateRequest
{
    protected function settingsGroup(): SettingsGroup
    {
        return SettingsGroup::Wallet;
    }
}
