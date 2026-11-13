<?php

namespace App\Modules\Wallet\Services;

use App\Models\User;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletDirection;
use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletHold;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seul point d'écriture du ledger Wallet : verrouille le compte, vérifie les
 * invariants, écrit la transaction append-only et met à jour le solde.
 */
final class WalletLedgerService
{
    /**
     * Crée une écriture de crédit sur un solde.
     *
     * @param  Wallet  $wallet  Wallet crédité
     * @param  WalletBalanceType  $type  Solde concerné
     * @param  int  $amount  Montant strictement positif
     * @param  WalletTransactionReason  $reason  Motif du crédit
     * @param  string  $idempotencyKey  Clé d'idempotence
     * @param  array<string, mixed>  $meta  Contexte libre de l'écriture
     * @param  Model|null  $reference  Objet métier d'origine
     * @param  User|null  $actor  Auteur de l'écriture
     * @param  int|null  $agencyId  Agence de rattachement
     * @return WalletTransaction Écriture créée ou rejouée
     */
    public function credit(
        Wallet $wallet,
        WalletBalanceType $type,
        int $amount,
        WalletTransactionReason $reason,
        string $idempotencyKey,
        array $meta = [],
        ?Model $reference = null,
        ?User $actor = null,
        ?int $agencyId = null,
    ): WalletTransaction {
        return $this->write(
            wallet: $wallet,
            type: $type,
            direction: WalletDirection::Credit,
            amount: $amount,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            meta: $meta,
            reference: $reference,
            actor: $actor,
            agencyId: $agencyId,
        );
    }

    /**
     * Crée une écriture de débit dans la limite du disponible.
     *
     * @param  Wallet  $wallet  Wallet débité
     * @param  WalletBalanceType  $type  Solde concerné
     * @param  int  $amount  Montant strictement positif
     * @param  WalletTransactionReason  $reason  Motif du débit
     * @param  string  $idempotencyKey  Clé d'idempotence
     * @param  array<string, mixed>  $meta  Contexte libre de l'écriture
     * @param  Model|null  $reference  Objet métier d'origine
     * @param  User|null  $actor  Auteur de l'écriture
     * @param  int|null  $agencyId  Agence de rattachement
     * @return WalletTransaction Écriture créée ou rejouée
     *
     * @throws ValidationException
     */
    public function debit(
        Wallet $wallet,
        WalletBalanceType $type,
        int $amount,
        WalletTransactionReason $reason,
        string $idempotencyKey,
        array $meta = [],
        ?Model $reference = null,
        ?User $actor = null,
        ?int $agencyId = null,
    ): WalletTransaction {
        return $this->write(
            wallet: $wallet,
            type: $type,
            direction: WalletDirection::Debit,
            amount: $amount,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            meta: $meta,
            reference: $reference,
            actor: $actor,
            agencyId: $agencyId,
        );
    }

    /**
     * Crédite des points puis absorbe la compensation due sur ce gain.
     *
     * @param  array<string, mixed>  $meta
     */
    public function creditPointsWithCompensation(
        Wallet $wallet,
        int $amount,
        WalletTransactionReason $reason,
        string $idempotencyKey,
        array $meta = [],
        ?Model $reference = null,
        ?User $actor = null,
        ?int $agencyId = null,
    ): ?WalletTransaction {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($wallet, $amount, $reason, $idempotencyKey, $meta, $reference, $actor, $agencyId): WalletTransaction {
            $transaction = $this->credit($wallet, WalletBalanceType::Points, $amount, $reason, $idempotencyKey, $meta, $reference, $actor, $agencyId);

            if (! $transaction->wasRecentlyCreated) {
                return $transaction;
            }

            $locked = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();
            $due = (int) $locked->points_compensation_due;

            if ($due <= 0) {
                return $transaction;
            }

            $absorbed = min($amount, $due);
            $this->debit(
                $locked,
                WalletBalanceType::Points,
                $absorbed,
                WalletTransactionReason::CompensationOffset,
                $idempotencyKey.':compensation',
                ['offset_for' => $reason->value],
            );

            $locked->refresh();
            $locked->points_compensation_due = max(0, $due - $absorbed);
            $locked->save();

            return $transaction;
        });
    }

    /**
     * Reprend des points indus : sur le disponible d'abord, le reliquat en compensation future.
     *
     * @param  array<string, mixed>  $meta
     */
    public function correctPoints(
        Wallet $wallet,
        int $amount,
        string $idempotencyKey,
        array $meta = [],
        ?Model $reference = null,
        ?User $actor = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($wallet, $amount, $idempotencyKey, $meta, $reference, $actor): void {
            $locked = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->claimCorrection($locked, $amount, $idempotencyKey)) {
                return;
            }

            $available = max(0, (int) $locked->points_balance - $this->heldAmount($locked, WalletBalanceType::Points));
            $recovered = min($amount, $available);

            if ($recovered > 0) {
                $this->debit(
                    $locked,
                    WalletBalanceType::Points,
                    $recovered,
                    WalletTransactionReason::PointsCorrection,
                    $idempotencyKey,
                    $meta,
                    $reference,
                    $actor,
                );
            }

            $remainder = $amount - $recovered;
            if ($remainder > 0) {
                $locked->refresh();
                $locked->points_compensation_due = (int) $locked->points_compensation_due + $remainder;
                $locked->save();
            }

            DB::table('wallet_points_corrections')
                ->where('idempotency_key', $idempotencyKey)
                ->update(['recovered' => $recovered, 'deferred' => $remainder]);
        });
    }

    /**
     * Réserve la clé de correction : une même reprise n'est appliquée qu'une fois,
     * y compris lorsque tout le montant part en compensation différée.
     */
    private function claimCorrection(Wallet $wallet, int $amount, string $idempotencyKey): bool
    {
        if (WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return false;
        }

        return DB::table('wallet_points_corrections')->insertOrIgnore([
            'wallet_id' => $wallet->getKey(),
            'idempotency_key' => $idempotencyKey,
            'amount' => $amount,
            'recovered' => 0,
            'deferred' => 0,
            'created_at' => now(),
        ]) > 0;
    }

    /**
     * Solde utilisable, holds actifs déduits.
     *
     * @param  Wallet  $wallet  Wallet consulté
     * @param  WalletBalanceType  $type  Solde concerné
     * @return int Montant disponible
     */
    public function availableBalance(Wallet $wallet, WalletBalanceType $type): int
    {
        $held = $this->heldAmount($wallet, $type);

        return max(0, $this->balance($wallet, $type) - $held);
    }

    /**
     * Somme des holds actifs sur un solde.
     *
     * @param  Wallet  $wallet  Wallet consulté
     * @param  WalletBalanceType  $type  Solde concerné
     * @return int Montant réservé
     */
    public function heldAmount(Wallet $wallet, WalletBalanceType $type): int
    {
        $column = $type === WalletBalanceType::Cash ? 'cash_amount' : 'points_amount';

        return (int) WalletHold::query()
            ->where('wallet_id', $wallet->getKey())
            ->where('status', WalletHoldStatus::Active->value)
            ->sum($column);
    }

    /**
     * Solde matérialisé, rechargé depuis la base.
     *
     * @param  Wallet  $wallet  Wallet consulté
     * @param  WalletBalanceType  $type  Solde concerné
     * @return int Solde courant
     */
    public function balance(Wallet $wallet, WalletBalanceType $type): int
    {
        $wallet->refresh();

        return $type === WalletBalanceType::Cash ? (int) $wallet->cash_balance : (int) $wallet->points_balance;
    }

    /**
     * Recalcule les soldes depuis le ledger pour contrôle d'intégrité.
     *
     * @param  Wallet  $wallet  Wallet contrôlé
     * @return array{cash: int, points: int} Soldes reconstruits
     */
    public function reconstructedBalances(Wallet $wallet): array
    {
        $sums = WalletTransaction::query()
            ->where('wallet_id', $wallet->getKey())
            ->selectRaw('balance_type, direction, SUM(amount) as total')
            ->groupBy('balance_type', 'direction')
            ->get();

        $balances = ['cash' => 0, 'points' => 0];

        foreach ($sums as $row) {
            $total = (int) $row->getAttribute('total');
            $signed = $row->getRawOriginal('direction') === WalletDirection::Credit->value ? $total : -$total;
            $type = (string) $row->getRawOriginal('balance_type');

            if (array_key_exists($type, $balances)) {
                $balances[$type] += $signed;
            }
        }

        return $balances;
    }

    /**
     * Applique les invariants du ledger sous verrou puis persiste l'écriture.
     *
     * @param  Wallet  $wallet  Wallet mouvementé
     * @param  WalletBalanceType  $type  Solde concerné
     * @param  WalletDirection  $direction  Sens de l'écriture
     * @param  int  $amount  Montant strictement positif
     * @param  WalletTransactionReason  $reason  Motif de l'écriture
     * @param  string  $idempotencyKey  Clé d'idempotence
     * @param  array<string, mixed>  $meta  Contexte libre
     * @param  Model|null  $reference  Objet métier d'origine
     * @param  User|null  $actor  Auteur de l'écriture
     * @param  int|null  $agencyId  Agence de rattachement
     * @return WalletTransaction Écriture créée ou rejouée
     *
     * @throws ValidationException
     */
    private function write(
        Wallet $wallet,
        WalletBalanceType $type,
        WalletDirection $direction,
        int $amount,
        WalletTransactionReason $reason,
        string $idempotencyKey,
        array $meta,
        ?Model $reference,
        ?User $actor,
        ?int $agencyId,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Le montant d’une écriture Wallet doit être strictement positif.',
            ]);
        }

        return DB::transaction(function () use ($wallet, $type, $direction, $amount, $reason, $idempotencyKey, $meta, $reference, $actor, $agencyId): WalletTransaction {
            $locked = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing;
            }

            $column = $type === WalletBalanceType::Cash ? 'cash_balance' : 'points_balance';

            if ($direction === WalletDirection::Debit) {
                $available = (int) $locked->{$column} - $this->heldAmount($locked, $type);

                if ($amount > $available) {
                    throw ValidationException::withMessages([
                        $type === WalletBalanceType::Cash ? 'wallet_amount' : 'points_amount' => $type === WalletBalanceType::Cash
                            ? 'Le solde Wallet disponible est insuffisant.'
                            : 'Le solde de points disponible est insuffisant.',
                    ]);
                }

                $locked->{$column} = (int) $locked->{$column} - $amount;
            } else {
                $locked->{$column} = (int) $locked->{$column} + $amount;
            }

            if ($reason->isQualifyingActivity()) {
                $locked->points_expires_at = Carbon::now()->addMonths(app(WalletSettings::class)->pointsExpiryMonths);
            }

            $locked->save();

            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $locked->getKey(),
                'balance_type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'actor_user_id' => $actor?->getKey(),
                'agency_id' => $agencyId,
                'loyalty_rule_version_id' => $type === WalletBalanceType::Points
                    ? WalletLoyaltyRuleVersion::query()
                        ->where('status', WalletLoyaltyRuleVersionStatus::Active->value)
                        ->value('id')
                    : null,
                'meta' => $meta === [] ? null : $meta,
                'created_at' => now(),
            ]);

            $wallet->setRawAttributes($locked->getAttributes(), true);

            return $transaction;
        });
    }
}
