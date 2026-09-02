<?php

namespace App\Modules\IdentityAccess\Contracts;

use App\Models\User;

interface PassengerIdentityResolver
{
    public function resolveByPhone(string $normalizedPhone): ?User;
}
