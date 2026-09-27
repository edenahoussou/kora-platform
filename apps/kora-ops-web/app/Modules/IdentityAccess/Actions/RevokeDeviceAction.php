<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\UserDevice;

class RevokeDeviceAction
{
    public function handle(UserDevice $device): void
    {
        $device->update([
            'status' => UserDeviceStatus::Revoked,
            'revoked_at' => now(),
            'push_token' => null,
        ]);

        $device->user
            ->tokens()
            ->where('name', "device:{$device->uuid}")
            ->delete();
    }
}
