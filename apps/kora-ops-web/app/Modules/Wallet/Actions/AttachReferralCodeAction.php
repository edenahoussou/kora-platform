<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletNotificationPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rattache le code parrain d'un autre Wallet, une seule fois et avant tout usage.
 */
final class AttachReferralCodeAction
{
    public function __construct(private WalletNotificationPublisher $notifications) {}

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
        return DB::transaction(function () use ($wallet, $code): Wallet {
            $wallet = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

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

            $this->notifications->referralAttached($referrer, $wallet);

            return $wallet;
        });
    }
}
