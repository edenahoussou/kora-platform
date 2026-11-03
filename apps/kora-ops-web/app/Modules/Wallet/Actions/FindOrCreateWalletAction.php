<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Provisionne le compte Wallet d'un voyageur identifié par son téléphone.
 */
final class FindOrCreateWalletAction
{
    public function __construct(private readonly PassengerIdentityResolver $identityResolver) {}

    public function handle(?User $user = null, ?string $phone = null, ?string $name = null): Wallet
    {
        if ($user === null) {
            $normalized = $this->identityResolver->normalizePhone($phone);

            if ($normalized === null) {
                throw ValidationException::withMessages([
                    'phone' => 'Un numéro de téléphone valide est requis pour ouvrir un Wallet.',
                ]);
            }

            $user = $this->identityResolver->resolveByPhone($normalized)
                ?? $this->identityResolver->resolveOrCreateByPhone($normalized, $name ?? $this->defaultName($normalized));
        }

        $existing = Wallet::query()->where('user_id', $user->getKey())->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return Wallet::query()->create([
                'user_id' => $user->getKey(),
                'referral_code' => $this->referralCode(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return Wallet::query()->where('user_id', $user->getKey())->firstOrFail();
        }
    }

    private function defaultName(string $normalizedPhone): string
    {
        return 'Voyageur '.Str::substr($normalizedPhone, -4);
    }

    private function referralCode(): string
    {
        do {
            $code = 'K'.Str::upper(Str::random(7));
        } while (Wallet::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
