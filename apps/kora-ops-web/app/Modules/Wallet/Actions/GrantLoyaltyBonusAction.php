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
    /**
     * @param  WalletSettings  $settings  Paramètres du module
     * @param  WalletLedgerService  $ledger  Écritures du ledger
     * @param  WalletNotificationPublisher  $notifications  Notifications voyageur
     */
    public function __construct(
        private readonly WalletSettings $settings,
        private readonly WalletLedgerService $ledger,
        private readonly WalletNotificationPublisher $notifications,
    ) {}

    /**
     * Accorde le bonus une seule fois par type et par période.
     *
     * @param  Wallet  $wallet  Wallet bénéficiaire
     * @param  LoyaltyBonusKind  $kind  Type de bonus
     * @param  string  $periodKey  Période d'unicité (mois, année, filleul)
     * @param  int|null  $points  Montant forcé, sinon paramètre du type
     * @return WalletTransaction|null Écriture créée, null si déjà accordé
     */
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

    /**
     * Lit le montant configuré pour un type de bonus.
     *
     * @param  LoyaltyBonusKind  $kind  Type de bonus
     * @return int Points à verser
     */
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
