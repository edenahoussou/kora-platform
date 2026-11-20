<?php

namespace App\Modules\Settings\Contracts;

use App\Models\User;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\Data\SettingsSnapshot;
use App\Modules\Settings\DTO\SettingsUpdate;

interface SettingsCoordinator
{
    public function snapshot(SettingsGroup $group): SettingsSnapshot;

    public function update(
        SettingsGroup $group,
        SettingsUpdate $update,
        User $actor,
    ): SettingsSnapshot;

    /** @param array<string, mixed> $values */
    public function updateParcelSettings(array $values, User $actor): SettingsSnapshot;
}
