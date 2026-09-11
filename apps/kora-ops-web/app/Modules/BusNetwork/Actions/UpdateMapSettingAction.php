<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\MapSetting;

class UpdateMapSettingAction
{
    /** @param array{provider: string|null} $attributes */
    public function execute(array $attributes, int $userId): MapSetting
    {
        $setting = MapSetting::query()->first();

        if ($setting) {
            $setting->update([...$attributes, 'updated_by' => $userId]);

            return $setting->refresh();
        }

        return MapSetting::query()->create([...$attributes, 'updated_by' => $userId]);
    }
}
