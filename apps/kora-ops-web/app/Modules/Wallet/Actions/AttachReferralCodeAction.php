<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Models\Wallet;
use Illuminate\Validation\ValidationException;

/**
 * Rattache le code parrain d'un autre Wallet, une seule fois et avant tout usage.
 */
final class AttachReferralCodeAction
{
    /**
     * Rattache le parrain une seule fois, avant tout usage du filleul.
     *
     * @param  Wallet  $wallet  Wallet du filleul
     * @param  string  $code  Code parrain saisi
     * @return Wallet Wallet rattaché
     *
     * @throws ValidationException
     */
    public function handle(Wallet $wallet, string $code): Wallet
    {
        $normalized = strtoupper(trim($code));

        if ($normalized === '' || $normalized === $wallet->referral_code) {
            throw ValidationException::withMessages([
                'referral_code' => 'Vous ne pouvez pas utiliser votre propre code de parrainage.',
            ]);
        }

        if ($wallet->referred_by_user_id !== null) {
            throw ValidationException::withMessages([
                'referral_code' => 'Un code de parrainage est déjà rattaché à ce compte.',
            ]);
        }

        if ($wallet->usages()->exists()) {
            throw ValidationException::withMessages([
                'referral_code' => 'Le code de parrainage doit être saisi avant la première utilisation.',
            ]);
        }

        $referrer = Wallet::query()->where('referral_code', $normalized)->first();

        if ($referrer === null) {
            throw ValidationException::withMessages([
                'referral_code' => 'Ce code de parrainage est inconnu.',
            ]);
        }

        $wallet->update(['referred_by_user_id' => $referrer->user_id]);

        return $wallet->fresh();
    }
}
