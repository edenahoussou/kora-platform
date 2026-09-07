<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhonePassengerIdentityResolver implements PassengerIdentityResolver
{
    public function normalizePhone(?string $phone): ?string
    {
        $normalized = preg_replace('/[^0-9+]/', '', trim((string) $phone));

        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        if (! str_starts_with($normalized, '+')) {
            $normalized = '+229'.$normalized;
        }

        return $normalized;
    }

    public function resolveByPhone(string $normalizedPhone): ?User
    {
        return User::query()->where('phone', $normalizedPhone)->first();
    }

    public function resolveOrCreateByPhone(string $normalizedPhone, ?string $name = null): User
    {
        $existing = $this->resolveByPhone($normalizedPhone);
        if ($existing !== null) {
            return $existing;
        }

        if ($name === null || trim($name) === '') {
            throw ValidationException::withMessages([
                'payer.name' => 'Le nom du payeur est obligatoire pour créer son compte voyageur.',
            ]);
        }

        return User::query()->create([
            'name' => trim($name),
            'email' => 'traveler+'.Str::uuid().'@accounts.kora.invalid',
            'phone' => $normalizedPhone,
            'password' => Str::random(64),
        ]);
    }
}
