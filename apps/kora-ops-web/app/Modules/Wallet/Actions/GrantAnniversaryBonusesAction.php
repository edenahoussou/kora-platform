<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Data\LoyaltyBonusKind;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Support\Carbon;

/**
 * Accorde le bonus d'anniversaire de création du compte, une fois par année civile.
 */
final class GrantAnniversaryBonusesAction
{
    /**
     * @param  GrantLoyaltyBonusAction  $grantBonus  Attribution des bonus
     */
    public function __construct(private readonly GrantLoyaltyBonusAction $grantBonus) {}

    /**
     * Accorde le bonus d'anniversaire aux comptes créés ce jour-là.
     *
     * @return int Nombre de bonus accordés
     */
    public function handle(): int
    {
        $today = Carbon::now('Africa/Porto-Novo');
        $granted = 0;

        Wallet::query()
            ->whereDate('created_at', '<=', $today->toDateString())
            ->whereMonth('created_at', $today->month)
            ->whereDay('created_at', $today->day)
            ->whereYear('created_at', '<', $today->year)
            ->chunkById(200, function ($wallets) use (&$granted, $today): void {
                foreach ($wallets as $wallet) {
                    $transaction = $this->grantBonus->handle(
                        $wallet,
                        LoyaltyBonusKind::Anniversary,
                        (string) $today->year,
                    );

                    if ($transaction !== null) {
                        $granted++;
                    }
                }
            });

        return $granted;
    }
}
