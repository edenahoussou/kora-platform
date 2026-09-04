<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;

class PhonePassengerIdentityResolver implements PassengerIdentityResolver
{
    public function resolveByPhone(string $normalizedPhone): ?User
    {
        return User::query()->where('phone', $normalizedPhone)->first();
    }
}
