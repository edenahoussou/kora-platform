<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\UserDevice;

class FindTrustedDeviceAction
{
    public function __construct(private HashDeviceFingerprintAction $hashDeviceFingerprint) {}

    public function handle(User $user, string $fingerprint): ?UserDevice
    {
        return UserDevice::query()
            ->whereBelongsTo($user)
            ->where('device_fingerprint_hash', $this->hashDeviceFingerprint->handle($fingerprint))
            ->where('status', UserDeviceStatus::Active->value)
            ->whereNull('revoked_at')
            ->first();
    }
}
