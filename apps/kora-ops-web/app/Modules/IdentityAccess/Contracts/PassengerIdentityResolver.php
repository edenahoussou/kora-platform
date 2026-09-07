<?php

namespace App\Modules\IdentityAccess\Contracts;

use App\Models\User;

interface PassengerIdentityResolver
{
    public function normalizePhone(?string $phone): ?string;

    public function resolveByPhone(string $normalizedPhone): ?User;

    public function resolveOrCreateByPhone(string $normalizedPhone, ?string $name = null): User;
}
