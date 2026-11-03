<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\LoyaltyBonusKind;
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
    public function __construct(
        private readonly WalletSettings $settings,
        private readonly WalletLedgerService $ledger,
        private readonly FindOrCreateWalletAction $findOrCreateWallet,
        private readonly GrantLoyaltyBonusAction $grantBonus,
        private readonly WalletNotificationPublisher $notifications,
    ) {}

    public function handle(Reservation $reservation): int
    {
        if (! $this->settings->walletPointsEnabled) {
            return 0;
        }

        $reservation->loadMissing(['segments', 'passengers.user', 'payer']);

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

        foreach ($boarded as $segment) {
            $wallet = $this->walletFor($reservation, $segment);

            if ($wallet === null) {
                continue;
            }

            $points = $this->pointsForLeg($reservation, $segment, $payment);

            if ($points <= 0) {
                continue;
            }

            $this->ledger->creditPointsWithCompensation(
                $wallet,
                $points,
                WalletTransactionReason::PointsEarn,
                'loyalty:segment:'.$segment->uuid,
                [
                    'reservation_uuid' => $reservation->uuid,
                    'leg_order' => (int) $segment->leg_order,
                    'departure_uuid' => $segment->departure?->uuid,
                ],
                $reservation,
            );

            $credited += $points;
            $this->notifications->pointsCredited($wallet, $points);
        }

        if ($credited > 0 || $boarded->isNotEmpty()) {
            $firstSegment = $boarded->sortBy('leg_order')->first();
            $wallet = $firstSegment !== null ? $this->walletFor($reservation, $firstSegment) : null;

            if ($wallet !== null) {
                $this->recordUsage($wallet, $reservation);
            }
        }

        return $credited;
    }

    private function walletFor(Reservation $reservation, ReservationSegment $segment): ?Wallet
    {
        $passenger = $reservation->passengers->firstWhere('passenger_index', $segment->passenger_index)
            ?? $reservation->passengers->firstWhere('id', $segment->reservation_passenger_id);

        $owner = $passenger?->user;

        if (! $owner instanceof User && $reservation->payer_type === PayerType::Personal) {
            $owner = $reservation->payer;
        }

        if (! $owner instanceof User) {
            return null;
        }

        return $this->findOrCreateWallet->handle($owner);
    }

    private function pointsForLeg(Reservation $reservation, ReservationSegment $segment, ?Payment $payment): int
    {
        $base = $this->legBase($reservation, $segment);

        if ($base <= 0) {
            return 0;
        }

        $pointsFunded = (int) ($payment?->provider_payload['points_amount'] ?? 0);
        $total = (int) $reservation->total_amount;
        $pointsShare = $total > 0 ? (int) floor($base * $pointsFunded / $total) : 0;
        $monetary = max(0, $base - $pointsShare);

        return (int) floor($monetary * $this->settings->loyaltyEarnRateBasisPoints / 10000);
    }

    private function legBase(Reservation $reservation, ReservationSegment $segment): int
    {
        $snapshot = $reservation->total_snapshot ?? [];
        $legs = is_array($snapshot['legs'] ?? null) ? $snapshot['legs'] : [];
        $index = max(0, (int) $segment->leg_order - 1);

        if (isset($legs[$index]['final_amount'])) {
            return (int) $legs[$index]['final_amount'];
        }

        $allocations = is_array($snapshot['passenger_allocations'] ?? null) ? $snapshot['passenger_allocations'] : [];
        $passengerIndex = $segment->passenger_index ?? 0;

        return (int) ($allocations[$passengerIndex] ?? $allocations[0] ?? 0);
    }

    private function recordUsage(Wallet $wallet, Reservation $reservation): void
    {
        $usage = DB::transaction(function () use ($wallet, $reservation): ?WalletUsage {
            $existing = WalletUsage::query()
                ->where('wallet_id', $wallet->getKey())
                ->where('reservation_id', $reservation->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return null;
            }

            return WalletUsage::query()->create([
                'wallet_id' => $wallet->getKey(),
                'reservation_id' => $reservation->getKey(),
                'used_at' => now(),
            ]);
        });

        if ($usage === null) {
            return;
        }

        $this->grantBonus->handle($wallet, LoyaltyBonusKind::Welcome, 'once');

        $totalUsages = WalletUsage::query()->where('wallet_id', $wallet->getKey())->count();
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

        if ($totalUsages === 1 && $wallet->referred_by_user_id !== null) {
            $referrer = Wallet::query()->where('user_id', $wallet->referred_by_user_id)->first();

            if ($referrer !== null) {
                $this->grantBonus->handle($referrer, LoyaltyBonusKind::Referral, 'referred:'.$wallet->uuid);
            }
        }
    }
}
