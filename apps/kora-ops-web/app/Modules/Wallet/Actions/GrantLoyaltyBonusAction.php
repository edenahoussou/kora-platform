<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\LoyaltyBonusKind;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletBonusGrant;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedgerService;
use App\Modules\Wallet\Services\WalletNotificationPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Accorde un bonus une seule fois par type et par période (mois, année, filleul).
 */
final class GrantLoyaltyBonusAction
{
    public function __construct(
        private readonly WalletSettings $settings,
        private readonly WalletLedgerService $ledger,
        private readonly WalletNotificationPublisher $notifications,
    ) {}

    public function handle(Wallet $wallet, LoyaltyBonusKind $kind, string $periodKey, ?int $points = null): ?WalletTransaction
    {
        $points ??= $this->pointsFor($kind);

        if ($points <= 0) {
            return null;
        }

        $transaction = DB::transaction(function () use ($wallet, $kind, $periodKey, $points): ?WalletTransaction {
            $grant = WalletBonusGrant::query()->firstOrCreate(
                [
                    'wallet_id' => $wallet->getKey(),
                    'kind' => $kind->value,
                    'period_key' => $periodKey,
                ],
                ['uuid' => (string) Str::uuid()],
            );

            if (! $grant->wasRecentlyCreated) {
                return null;
            }

            $transaction = $this->ledger->creditPointsWithCompensation(
                $wallet,
                $points,
                $kind->reason(),
                'bonus:'.$kind->value.':'.$wallet->uuid.':'.$periodKey,
                ['bonus_kind' => $kind->value, 'period_key' => $periodKey],
                $wallet,
            );

            if ($transaction !== null) {
                $grant->update(['transaction_id' => $transaction->getKey()]);
            }

            return $transaction;
        });

        if ($transaction !== null) {
            $this->notifications->bonusGranted($wallet, $kind, $points);
        }

        return $transaction;
    }

    private function pointsFor(LoyaltyBonusKind $kind): int
    {
        return match ($kind) {
            LoyaltyBonusKind::Welcome => $this->settings->bonusWelcomePoints,
            LoyaltyBonusKind::Referral => $this->settings->bonusReferralPoints,
            LoyaltyBonusKind::Anniversary => $this->settings->bonusAnniversaryPoints,
            LoyaltyBonusKind::Usage => $this->settings->bonusUsagePoints,
        };
    }
}
