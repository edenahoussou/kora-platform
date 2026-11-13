<?php

namespace App\Modules\Wallet\Services;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletDirection;
use App\Modules\Wallet\Data\WalletOpsAccountData;
use App\Modules\Wallet\Data\WalletOpsDashboardData;
use App\Modules\Wallet\Data\WalletOpsMovementData;
use App\Modules\Wallet\Data\WalletTopUpStatus;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletRestitution;
use App\Modules\Wallet\Models\WalletTopUp;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

/**
 * Read-only OPS interface for BUS wallets, accounts, and ledger movements.
 *
 * Returned DTOs contain public UUIDs only. This reader never provisions a wallet.
 */
final class OpsWalletReader
{
    public function __construct(private readonly WalletSettings $settings) {}

    public function dashboard(): WalletOpsDashboardData
    {
        $deadline = now()->addDays($this->expiryReminderWindowDays());
        $expiring = Wallet::query()
            ->where('points_balance', '>', 0)
            ->whereBetween('points_expires_at', [now(), $deadline]);
        $pendingTopUps = WalletTopUp::query()->where('status', WalletTopUpStatus::Pending->value);
        $pendingRestitutions = WalletRestitution::query()->whereIn('status', [
            RestitutionStatus::Requested->value,
            RestitutionStatus::Approved->value,
        ]);

        $recent = WalletTransaction::query()
            ->with([
                'wallet.user',
                'agency',
                'actor',
                'loyaltyRuleVersion',
                'reference' => function (Relation $relation): void {
                    $this->eagerLoadPaymentReservation($relation);
                },
            ])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (WalletTransaction $transaction): array => $this->movementData($transaction)->toArray())
            ->values()
            ->all();

        return new WalletOpsDashboardData(
            accountCount: Wallet::query()->count(),
            cashTotal: (int) Wallet::query()->sum('cash_balance'),
            pointsTotal: (int) Wallet::query()->sum('points_balance'),
            pointsValue: (int) Wallet::query()->sum('points_balance') * $this->settings->pointValueFcfa,
            expiringAccountCount: (int) (clone $expiring)->count(),
            expiringPoints: (int) (clone $expiring)->sum('points_balance'),
            expiringValue: (int) (clone $expiring)->sum('points_balance') * $this->settings->pointValueFcfa,
            pendingTopUpCount: (int) (clone $pendingTopUps)->count(),
            pendingTopUpAmount: (int) (clone $pendingTopUps)->sum('amount'),
            pendingRestitutionCount: (int) (clone $pendingRestitutions)->count(),
            pendingRestitutionAmount: (int) (clone $pendingRestitutions)->sum('amount'),
            recentMovements: array_values($recent),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function accounts(array $filters): LengthAwarePaginator
    {
        $query = Wallet::query()
            ->with(['user', 'latestTransaction.agency'])
            ->latest('created_at');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('uuid', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%')
                            ->orWhere('uuid', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && in_array($status, ['active', 'suspended', 'locked'], true)) {
            $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('status', $status));
        }

        $balanceType = $filters['balance_type'] ?? null;
        if ($balanceType === WalletBalanceType::Cash->value) {
            $query->where('cash_balance', '>', 0);
        } elseif ($balanceType === WalletBalanceType::Points->value) {
            $query->where('points_balance', '>', 0);
        }

        if (($filters['expiration'] ?? null) === 'near') {
            $query->where('points_balance', '>', 0)
                ->whereBetween('points_expires_at', [now(), now()->addDays($this->expiryReminderWindowDays())]);
        }

        if (filled($filters['agency_uuid'] ?? null)) {
            $agencyUuid = (string) $filters['agency_uuid'];
            $query->whereHas('latestTransaction.agency', fn (Builder $agencyQuery) => $agencyQuery->where('uuid', $agencyUuid));
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate($this->perPage($filters))->withQueryString()->through(
            fn (Wallet $wallet): array => $this->accountData($wallet)->toArray(),
        );
    }

    public function account(Wallet $wallet): WalletOpsAccountData
    {
        $wallet->loadMissing(['user', 'latestTransaction.agency']);
        $movements = $wallet->transactions()
            ->with([
                'wallet.user',
                'agency',
                'actor',
                'loyaltyRuleVersion',
                'reference' => function (Relation $relation): void {
                    $this->eagerLoadPaymentReservation($relation);
                },
            ])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (WalletTransaction $transaction): array => $this->movementData($transaction)->toArray())
            ->values()
            ->all();

        return $this->accountData($wallet, array_values($movements));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function movements(array $filters): LengthAwarePaginator
    {
        $query = WalletTransaction::query()
            ->with([
                'wallet.user',
                'agency',
                'actor',
                'loyaltyRuleVersion',
                'reference' => function (Relation $relation): void {
                    $this->eagerLoadPaymentReservation($relation);
                },
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $pattern = '%'.$search.'%';
            $query->where(function (Builder $query) use ($pattern): void {
                $query->where('uuid', 'like', $pattern)
                    ->orWhereHas('wallet', function (Builder $walletQuery) use ($pattern): void {
                        $walletQuery->where('uuid', 'like', $pattern)
                            ->orWhereHas('user', function (Builder $userQuery) use ($pattern): void {
                                $userQuery->where('name', 'like', $pattern)
                                    ->orWhere('phone', 'like', $pattern)
                                    ->orWhere('uuid', 'like', $pattern);
                            });
                    });

                $query->orWhereHasMorph(
                    'reference',
                    [Reservation::class, Payment::class, WalletRestitution::class, WalletTopUp::class, Wallet::class],
                    function (Builder $sourceQuery, string $type) use ($pattern): void {
                        $sourceQuery->where('uuid', 'like', $pattern);

                        if ($type === Payment::class) {
                            $sourceQuery->orWhereHas('reservation', fn (Builder $reservationQuery) => $reservationQuery->where('uuid', 'like', $pattern));
                        }
                    },
                );
            });
        }

        foreach ([
            'balance_type' => WalletBalanceType::values(),
            'direction' => WalletDirection::values(),
            'reason' => WalletTransactionReason::values(),
        ] as $field => $allowed) {
            if (is_string($filters[$field] ?? null) && in_array($filters[$field], $allowed, true)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['agency_uuid'] ?? null)) {
            $agencyUuid = (string) $filters['agency_uuid'];
            $query->whereHas('agency', fn (Builder $agencyQuery) => $agencyQuery->where('uuid', $agencyUuid));
        }

        if (filled($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (filled($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate($this->perPage($filters))->withQueryString()->through(
            fn (WalletTransaction $transaction): array => $this->movementData($transaction)->toArray(),
        );
    }

    public function movement(WalletTransaction $transaction): WalletOpsMovementData
    {
        $transaction->loadMissing([
            'wallet.user',
            'agency',
            'actor',
            'loyaltyRuleVersion',
            'reference' => function (Relation $relation): void {
                $this->eagerLoadPaymentReservation($relation);
            },
        ]);

        return $this->movementData($transaction);
    }

    /** @param Relation<*, *, *> $relation */
    private function eagerLoadPaymentReservation(Relation $relation): void
    {
        if ($relation instanceof MorphTo) {
            $relation->morphWith([Payment::class => ['reservation']]);
        }
    }

    /** @param list<array<string, mixed>> $recentMovements */
    private function accountData(Wallet $wallet, array $recentMovements = []): WalletOpsAccountData
    {
        $latest = $wallet->latestTransaction;

        return new WalletOpsAccountData(
            uuid: (string) $wallet->uuid,
            holder: [
                'uuid' => (string) $wallet->user->uuid,
                'name' => $wallet->user->name,
                'phone' => $wallet->user->phone,
                'status' => $wallet->user->status->value,
            ],
            balances: [
                'cash' => (int) $wallet->cash_balance,
                'points' => (int) $wallet->points_balance,
                'pointsValue' => (int) $wallet->points_balance * $this->settings->pointValueFcfa,
            ],
            pointsExpiresAt: $wallet->points_expires_at?->toIso8601String(),
            pointsCompensationDue: (int) $wallet->points_compensation_due,
            lastActivityAt: $latest?->created_at?->toIso8601String(),
            agency: $latest?->agency === null ? null : [
                'uuid' => (string) $latest->agency->uuid,
                'name' => (string) $latest->agency->name,
            ],
            createdAt: $wallet->created_at->toIso8601String(),
            recentMovements: $recentMovements,
        );
    }

    private function movementData(WalletTransaction $transaction): WalletOpsMovementData
    {
        $wallet = $transaction->wallet;
        $user = $wallet->user;

        return new WalletOpsMovementData(
            uuid: (string) $transaction->uuid,
            walletUuid: (string) $wallet->uuid,
            holder: [
                'uuid' => (string) $user->uuid,
                'name' => $user->name,
                'phone' => $user->phone,
            ],
            balanceType: $transaction->balance_type->value,
            direction: $transaction->direction->value,
            amount: (int) $transaction->amount,
            reason: $transaction->reason->value,
            reasonLabel: $this->reasonLabel($transaction->reason),
            source: $this->source($transaction),
            agency: $transaction->agency === null ? null : [
                'uuid' => (string) $transaction->agency->uuid,
                'name' => (string) $transaction->agency->name,
            ],
            loyaltyRuleVersion: $transaction->loyaltyRuleVersion === null ? null : [
                'uuid' => (string) $transaction->loyaltyRuleVersion->uuid,
                'version' => (int) $transaction->loyaltyRuleVersion->version,
            ],
            actorName: $transaction->actor?->name,
            createdAt: $transaction->created_at->toIso8601String(),
        );
    }

    /** @return array{type: string, label: string, uuid: string, url: string|null}|null */
    private function source(WalletTransaction $transaction): ?array
    {
        $reference = $transaction->reference;
        if ($reference === null || ! filled($reference->getAttribute('uuid'))) {
            return null;
        }

        $uuid = (string) $reference->getAttribute('uuid');
        $type = match (true) {
            $reference instanceof Reservation => 'reservation',
            $reference instanceof Payment => 'payment',
            $reference instanceof WalletRestitution => 'restitution',
            $reference instanceof WalletTopUp => 'topup',
            $reference instanceof Wallet => 'wallet',
            default => 'bus_operation',
        };
        $url = null;
        $label = 'Opération BUS';

        if ($reference instanceof Reservation) {
            $label = 'Réservation BUS';
            $url = Route::has('manifest.reservations.show') ? route('manifest.reservations.show', $uuid) : null;
        } elseif ($reference instanceof Payment) {
            $reservation = $reference->reservation;
            $label = $transaction->reason === WalletTransactionReason::TopUp
                ? 'Paiement de recharge'
                : 'Paiement BUS';
            if ($reservation !== null) {
                $uuid = (string) $reservation->uuid;
                $url = Route::has('manifest.reservations.show')
                    ? route('manifest.reservations.show', $reservation->uuid)
                    : null;
            }
        } elseif ($reference instanceof WalletRestitution) {
            $label = 'Restitution Wallet';
            $url = Route::has('administration.wallet-restitutions.index')
                ? route('administration.wallet-restitutions.index', ['source' => $uuid])
                : null;
        } elseif ($reference instanceof WalletTopUp) {
            $label = 'Recharge Wallet';
        } elseif ($reference instanceof Wallet) {
            $label = 'Compte Wallet';
            $url = Route::has('wallet.accounts.show') ? route('wallet.accounts.show', $reference->uuid) : null;
        }

        return ['type' => $type, 'label' => $label, 'uuid' => $uuid, 'url' => $url];
    }

    private function expiryReminderWindowDays(): int
    {
        return $this->settings->pointsExpiryReminderDays === []
            ? 30
            : max($this->settings->pointsExpiryReminderDays);
    }

    private function reasonLabel(WalletTransactionReason $reason): string
    {
        return match ($reason) {
            WalletTransactionReason::TopUp => 'Recharge',
            WalletTransactionReason::Purchase => 'Achat de billet BUS',
            WalletTransactionReason::RefundCash => 'Remboursement cash',
            WalletTransactionReason::RefundPoints => 'Remboursement de points',
            WalletTransactionReason::PointsEarn => 'Points gagnés',
            WalletTransactionReason::BonusWelcome => 'Bonus de bienvenue',
            WalletTransactionReason::BonusReferral => 'Bonus de parrainage',
            WalletTransactionReason::BonusAnniversary => 'Bonus anniversaire',
            WalletTransactionReason::BonusUsage => 'Bonus de fidélité',
            WalletTransactionReason::PointsExpire => 'Expiration de points',
            WalletTransactionReason::PointsCorrection => 'Correction de points',
            WalletTransactionReason::CompensationOffset => 'Compensation de points',
            WalletTransactionReason::Restitution => 'Restitution',
            WalletTransactionReason::Adjustment => 'Ajustement audité',
            WalletTransactionReason::UnconfirmedBookingSettlement => 'Régularisation réservation',
        };
    }

    /** @param array<string, mixed> $filters */
    private function perPage(array $filters): int
    {
        return max(10, min(100, (int) ($filters['per_page'] ?? 25)));
    }
}
