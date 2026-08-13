<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\RevokeDeviceAction;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\UserDevice;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserDeviceService
{
    public function __construct(private RevokeDeviceAction $revokeDevice) {}

    /**
     * @return Collection<int, UserDevice>
     */
    public function listForUser(User $user): Collection
    {
        return UserDevice::query()
            ->whereBelongsTo($user)
            ->where('status', UserDeviceStatus::Active->value)
            ->whereNull('revoked_at')
            ->orderByDesc('last_seen_at')
            ->get();
    }

    public function revokeForUser(User $user, UserDevice $device): void
    {
        if ($device->user_id !== $user->id) {
            throw new NotFoundHttpException;
        }

        $this->revokeDevice->handle($device);
    }
}
