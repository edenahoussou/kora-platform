<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\Notifications\Channels\OtpCodeChannel;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class PhonePassengerIdentityResolver implements PassengerIdentityResolver
{
    public function normalizePhone(?string $phone, ?string $countryCode = null): ?string
    {
        $rawPhone = trim((string) $phone);
        $normalized = preg_replace('/\D+/', '', $rawPhone);

        if ($normalized === null || $normalized === '') {
            return null;
        }

        $dialCode = $this->resolveDialCode($countryCode);

        if (str_starts_with($rawPhone, '+')) {
            return '+'.$normalized;
        }

        if (str_starts_with($normalized, '00')) {
            return '+'.substr($normalized, 2);
        }

        if (str_starts_with($normalized, $dialCode) && strlen($normalized) > strlen($dialCode) + 6) {
            return '+'.$normalized;
        }

        return '+'.$dialCode.$normalized;
    }

    private function resolveDialCode(?string $countryCode): string
    {
        $countryCode = strtoupper(trim((string) $countryCode));

        if ($countryCode === '') {
            return '229';
        }

        $numericCode = preg_replace('/\D+/', '', $countryCode);

        if ($numericCode !== '') {
            return $numericCode;
        }

        return match ($countryCode) {
            'BJ' => '229',
            'BF' => '226',
            'CI' => '225',
            'GH' => '233',
            'GN' => '224',
            'ML' => '223',
            'NE' => '227',
            'NG' => '234',
            'SN' => '221',
            'TG' => '228',
            default => '229',
        };
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

        $user = User::query()->create([
            'registration_origin' => 'assisted',
            'name' => trim($name),
            'email' => 'traveler+'.Str::uuid().'@'.OtpCodeChannel::PLACEHOLDER_EMAIL_DOMAIN,
            'phone' => $normalizedPhone,
            'password' => Str::random(64),
        ]);

        $user->assignRole(Role::findOrCreate(SystemRole::Passenger->value, 'web'));

        return $user;
    }
}
