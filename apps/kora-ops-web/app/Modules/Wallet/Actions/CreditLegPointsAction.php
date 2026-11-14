<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\LoyaltyBonusKind;
use App\Modules\Wallet\Data\WalletPurchaseFunding;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletUsage;
use App\Modules\Wallet\Services\WalletLedgerService;
use App\Modules\Wallet\Services\WalletNotificationPublisher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Crédite les points des jambes embarquées d'un billet et évalue les bonus liés à l'usage.
 */
final class CreditLegPointsAction
{
    /**
     * @param  WalletSettings  $settings  Paramètres du module
     * @param  WalletLedgerService  $ledger  Écritures du ledger
     * @param  FindOrCreateWalletAction  $findOrCreateWallet  Provisionnement des Wallets
     * @param  GrantLoyaltyBonusAction  $grantBonus  Attribution des bonus
     * @param  WalletNotificationPublisher  $notifications  Notifications voyageur
     */
    public function __construct(
        private readonly WalletSettings $settings,
        private readonly WalletLedgerService $ledger,
        private readonly FindOrCreateWalletAction $findOrCreateWallet,
        private readonly GrantLoyaltyBonusAction $grantBonus,
        private readonly WalletNotificationPublisher $notifications,
    ) {}

    /**
     * Crédite les jambes embarquées et évalue les bonus d'usage.
     *
     * @param  Reservation  $reservation  Réservation à créditer
     * @return int Points crédités
     */
    public function handle(Reservation $reservation): int
    {
        if (! $this->settings->walletPointsEnabled) {
            return 0;
        }

        $reservation->loadMissing(['segments.reservationPassenger', 'passengers.user']);

        $boarded = $reservation->segments
            ->filter(fn (ReservationSegment $segment): bool => $segment->status === ReservationSegmentStatus::Boarded);

        if ($boarded->isEmpty()) {
            return 0;
        }

        $payment = $reservation->payments()
            ->where('status', PaymentStatus::Confirmed->value)
            ->latest('id')
            ->first();

        $credited = 0;
        $usageWallets = [];

        foreach ($boarded as $segment) {
            $wallet = $this->walletFor($reservation, $segment);

            if ($wallet === null) {
                continue;
            }

            $usageWallets[$wallet->getKey()] = $wallet;

            $points = $this->pointsForLeg($reservation, $segment, $payment);

            if ($points <= 0) {
                continue;
            }

            $transaction = $this->ledger->creditPointsWithCompensation(
                $wallet,
                $points,
                WalletTransactionReason::PointsEarn,
                'loyalty:segment:'.$segment->uuid,
                [
                    'reservation_uuid' => $reservation->uuid,
                    'passenger_uuid' => $segment->reservationPassenger?->uuid,
                    'leg_order' => (int) $segment->leg_order,
                    'departure_uuid' => $segment->departure?->uuid,
                ],
                $reservation,
            );

            if ($transaction === null || ! $transaction->wasRecentlyCreated) {
                continue;
            }

            $credited += $points;
            $this->notifications->pointsCredited($wallet, $points);
        }

        foreach ($usageWallets as $wallet) {
            $this->recordUsage($wallet, $reservation);
        }

        return $credited;
    }

    /**
     * Résout le Wallet du voyageur propre à la jambe.
     *
     * @param  Reservation  $reservation  Réservation concernée
     * @param  ReservationSegment  $segment  Jambe embarquée
     * @return Wallet|null Wallet créditable, null sans compte voyageur
     */
    private function walletFor(Reservation $reservation, ReservationSegment $segment): ?Wallet
    {
        $passenger = $reservation->passengers->firstWhere('passenger_index', $segment->passenger_index)
            ?? $reservation->passengers->firstWhere('id', $segment->reservation_passenger_id);

        $owner = $passenger?->user;

        if (! $owner instanceof User) {
            return null;
        }

        return $this->findOrCreateWallet->handle($owner);
    }

    /**
     * Applique le taux configuré à la part monétaire de la jambe (floor).
     *
     * @param  Reservation  $reservation  Réservation concernée
     * @param  ReservationSegment  $segment  Jambe embarquée
     * @param  Payment|null  $payment  Paiement confirmé portant le financement
     * @return int Points à créditer
     */
    private function pointsForLeg(Reservation $reservation, ReservationSegment $segment, ?Payment $payment): int
    {
        $base = $this->legBase($reservation, $segment);

        if ($base <= 0) {
            return 0;
        }

        $pointsFunded = $payment === null ? 0 : WalletPurchaseFunding::fromPayment($payment)->pointsValue;
        $total = (int) $reservation->total_amount;
        $monetary = $total > 0 ? intdiv($base * max(0, $total - $pointsFunded), $total) : $base;

        return (int) floor($monetary * $this->settings->loyaltyEarnRateBasisPoints / 10000);
    }

    /**
     * Lit l'allocation figée de la jambe dans le snapshot du devis.
     *
     * @param  Reservation  $reservation  Réservation concernée
     * @param  ReservationSegment  $segment  Jambe embarquée
     * @return int Montant figé de la jambe
     */
    private function legBase(Reservation $reservation, ReservationSegment $segment): int
    {
        $snapshot = $reservation->total_snapshot ?? [];
        $legs = is_array($snapshot['legs'] ?? null) ? $snapshot['legs'] : [];
        $legIndex = max(0, (int) $segment->leg_order - 1);
        $passengerIndex = (int) ($segment->passenger_index ?? 0);
        $legAllocations = $snapshot['passenger_leg_allocations'] ?? null;

        if (is_array($legAllocations) && isset($legAllocations[$passengerIndex][$legIndex])) {
            return (int) $legAllocations[$passengerIndex][$legIndex];
        }

        $passengerAllocations = is_array($snapshot['passenger_allocations'] ?? null)
            ? $snapshot['passenger_allocations']
            : [];
        $passengerTotal = (int) ($passengerAllocations[$passengerIndex] ?? $passengerAllocations[0] ?? 0);

        if ($passengerTotal <= 0 || $legs === []) {
            return $passengerTotal;
        }

        $weights = array_map(
            static fn (array $leg): int => max(0, (int) ($leg['final_amount'] ?? 0)),
            $legs,
        );
        $weightTotal = array_sum($weights);

        if ($weightTotal <= 0 || ! array_key_exists($legIndex, $weights)) {
            return 0;
        }

        $shares = [];
        $remainders = [];
        foreach ($weights as $index => $weight) {
            $exact = $passengerTotal * $weight / $weightTotal;
            $shares[$index] = (int) floor($exact);
            $remainders[$index] = $exact - $shares[$index];
        }

        $remaining = $passengerTotal - array_sum($shares);
        $indices = array_keys($weights);
        usort($indices, static fn (int $left, int $right): int => ($remainders[$right] <=> $remainders[$left]) ?: ($left <=> $right));

        for ($index = 0; $index < $remaining; $index++) {
            $shares[$indices[$index % count($indices)]]++;
        }

        return (int) ($shares[$legIndex] ?? 0);
    }

    /**
     * Compte une utilisation par billet puis évalue les bonus associés.
     *
     * @param  Wallet  $wallet  Wallet du voyageur
     * @param  Reservation  $reservation  Billet utilisé
     */
    private function recordUsage(Wallet $wallet, Reservation $reservation): void
    {
        $usageNumber = DB::transaction(function () use ($wallet, $reservation): ?int {
            $wallet->refreshForUpdate();

            $existing = WalletUsage::query()
                ->where('wallet_id', $wallet->getKey())
                ->where('reservation_id', $reservation->getKey())
                ->first();

            if ($existing !== null) {
                return null;
            }

            WalletUsage::query()->create([
                'wallet_id' => $wallet->getKey(),
                'reservation_id' => $reservation->getKey(),
                'used_at' => now(),
            ]);

            return $wallet->usages()->count();
        });

        if ($usageNumber === null) {
            return;
        }

        $this->grantBonus->handle($wallet, LoyaltyBonusKind::Welcome, 'once');

        $monthKey = Carbon::now('Africa/Porto-Novo')->format('Y-m');
        $monthlyUsages = WalletUsage::query()
            ->where('wallet_id', $wallet->getKey())
            ->whereBetween('used_at', [
                Carbon::now('Africa/Porto-Novo')->startOfMonth(),
                Carbon::now('Africa/Porto-Novo')->endOfMonth(),
            ])
            ->count();

        if ($monthlyUsages === $this->settings->bonusUsageNth) {
            $this->grantBonus->handle($wallet, LoyaltyBonusKind::Usage, $monthKey);
        }

        if ($usageNumber === 1 && $wallet->referred_by_user_id !== null) {
            $referrer = Wallet::query()->where('user_id', $wallet->referred_by_user_id)->first();

            if ($referrer !== null) {
                $this->grantBonus->handle($referrer, LoyaltyBonusKind::Referral, 'referred:'.$wallet->uuid);
            }
        }
    }
}
