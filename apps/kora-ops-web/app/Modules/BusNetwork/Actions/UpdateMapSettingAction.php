<?php

namespace App\Modules\BusNetwork\Actions;

use App\Models\User;
use App\Modules\BusNetwork\Models\MapSetting;
use App\Modules\Settings\Contracts\SettingsCoordinator;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\DTO\IntegrationsSettingsUpdate;

class UpdateMapSettingAction
{
    public function __construct(private readonly SettingsCoordinator $settings) {}

    /** @param array{provider: string|null} $attributes */
    public function execute(array $attributes, User $user): MapSetting
    {
        $provider = $attributes['provider'] ?? (string) config('services.maps.default_provider', 'openstreetmap');

        $this->settings->update(
            SettingsGroup::Integrations,
            new IntegrationsSettingsUpdate(['mapProvider' => $provider]),
            $user,
        );

        $setting = MapSetting::query()->first();

        if ($setting) {
            $setting->update([...$attributes, 'updated_by' => $user->id]);

            return $setting->refresh();
        }

        return MapSetting::query()->create([...$attributes, 'updated_by' => $user->id]);
    }
}
