<?php

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Data\SettingsGroup;

final class UpdateGeneralSettingsRequest extends SettingsUpdateRequest
{
    protected function settingsGroup(): SettingsGroup
    {
        return SettingsGroup::General;
    }
}
