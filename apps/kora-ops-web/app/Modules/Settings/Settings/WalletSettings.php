<?php

namespace App\Modules\Settings\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Paramètres du Wallet et de la fidélité KORA BUS, éditables depuis l'écran
 * Paramètres OPS (section « Wallet & fidélité ») et audités à chaque mutation.
 */
final class WalletSettings extends Settings
{
    public bool $walletCashEnabled = false;

    public bool $walletPointsEnabled = true;

    public int $loyaltyEarnRateBasisPoints = 200;

    public int $pointValueFcfa = 1;

    public int $pointsMinSpend = 100;

    public int $bonusWelcomePoints = 500;

    public int $bonusReferralPoints = 200;

    public int $bonusAnniversaryPoints = 100;

    public int $bonusUsageNth = 5;

    public int $bonusUsagePoints = 50;

    public int $pointsExpiryMonths = 6;

    /** @var array<int, int> */
    public array $pointsExpiryReminderDays = [30, 7, 1];

    public int $walletHoldTtlMinutes = 15;

    public ?int $topupMinAmount = null;

    public ?int $topupMaxAmount = null;

    public ?int $cashBalanceCap = null;

    /** @var array<int, int> */
    public array $topupAmountPresets = [2000, 5000, 10000, 20000];

    /**
     * Groupe de paramètres exposé dans l'écran Paramètres OPS.
     *
     * @return string Nom du groupe `wallet`
     */
    public static function group(): string
    {
        return 'wallet';
    }
}
