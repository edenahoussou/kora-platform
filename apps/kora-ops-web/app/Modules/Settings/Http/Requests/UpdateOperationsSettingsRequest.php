<?php

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Data\SettingsGroup;

final class UpdateOperationsSettingsRequest extends SettingsUpdateRequest
{
    protected function settingsGroup(): SettingsGroup
    {
        return SettingsGroup::Operations;
    }
}
