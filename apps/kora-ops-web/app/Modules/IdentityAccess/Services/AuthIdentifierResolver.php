<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AuthIdentifierResolver
{
    public function __construct(
        private readonly PassengerIdentityResolver $phoneResolver,
    ) {}

    public function normalize(string $identifier): string
    {
        $identifier = trim($identifier);

        if ($this->isEmail($identifier)) {
            return Str::lower($identifier);
        }

        $phone = $this->phoneResolver->normalizePhone($identifier);

        if (! $this->isValidPhone($phone)) {
            throw new InvalidArgumentException('The authentication identifier is invalid.');
        }

        return $phone;
    }

    public function findUser(string $identifier): ?User
    {
        $normalized = $this->normalize($identifier);

        if ($this->isEmail($normalized)) {
            return User::query()->where('email', $normalized)->first();
        }

        return $this->phoneResolver->resolveByPhone($normalized);
    }

    public function isValidIdentifier(?string $identifier): bool
    {
        if ($identifier === null || trim($identifier) === '') {
            return false;
        }

        try {
            $this->normalize($identifier);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function normalizeForRateLimit(?string $identifier): string
    {
        $identifier = trim((string) $identifier);

        if ($identifier === '') {
            return '';
        }

        try {
            return $this->normalize($identifier);
        } catch (InvalidArgumentException) {
            return Str::lower($identifier);
        }
    }

    public function normalizePhone(?string $phone, ?string $countryCode = null): ?string
    {
        return $this->phoneResolver->normalizePhone($phone, $countryCode);
    }

    private function isEmail(string $identifier): bool
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isValidPhone(?string $phone): bool
    {
        if ($phone === null || ! str_starts_with($phone, '+')) {
            return false;
        }

        $digits = substr($phone, 1);

        return ctype_digit($digits) && strlen($digits) >= 7 && strlen($digits) <= 15;
    }
}
