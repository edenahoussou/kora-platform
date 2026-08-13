<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\UserDevice;
use Laravel\Sanctum\NewAccessToken;

class IssueDeviceTokenAction
{
    public function handle(User $user, UserDevice $device): NewAccessToken
    {
        return $user->createToken(
            name: "device:{$device->uuid}",
            abilities: ['*'],
            expiresAt: now()->addDays(7),
        );
    }
}
