<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\UserDevice;

class UpsertTrustedDeviceAction
{
    public function __construct(private HashDeviceFingerprintAction $hashDeviceFingerprint) {}

    /**
     * @param  array{name: string, platform: string, fingerprint: string, push_token?: string|null}  $device
     */
    public function handle(User $user, array $device): UserDevice
    {
        $fingerprintHash = $this->hashDeviceFingerprint->handle($device['fingerprint']);

        $userDevice = UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_fingerprint_hash' => $fingerprintHash,
            ],
            [
                'name' => $device['name'],
                'platform' => DevicePlatform::from($device['platform']),
                'push_token' => $device['push_token'] ?? null,
                'trusted_at' => now(),
                'last_seen_at' => now(),
                'revoked_at' => null,
                'status' => UserDeviceStatus::Active,
            ],
        );

        return $userDevice->refresh();
    }
}
