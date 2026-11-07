<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\WalletRestitution;
use App\Modules\Wallet\Services\WalletLedgerService;
use App\Modules\Wallet\Services\WalletNotificationPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trace le versement hors système puis débite le Wallet une seule fois.
 */
final class MarkRestitutionPaidAction
{
    /**
     * @param  WalletLedgerService  $ledger  Écritures du ledger
     * @param  WalletNotificationPublisher  $notifications  Notifications voyageur
     */
    public function __construct(
        private readonly WalletLedgerService $ledger,
        private readonly WalletNotificationPublisher $notifications,
    ) {}

    /**
     * Trace le versement puis débite le Wallet une seule fois.
     *
     * @param  WalletRestitution  $restitution  Restitution approuvée
     * @param  User  $actor  Responsable du versement
     * @param  string  $payoutReference  Référence du versement hors système
     * @param  string|null  $payoutChannel  Canal de versement
     * @return WalletRestitution Restitution versée
     *
     * @throws ValidationException
     */
    public function handle(
        WalletRestitution $restitution,
        User $actor,
        string $payoutReference,
        ?string $payoutChannel = null,
    ): WalletRestitution {
        if ($restitution->status !== RestitutionStatus::Approved) {
            throw ValidationException::withMessages(['status' => 'Seule une restitution approuvée peut être marquée versée.']);
        }

        if (trim($payoutReference) === '') {
            throw ValidationException::withMessages(['payout_reference' => 'La référence du versement est obligatoire.']);
        }

        $restitution = DB::transaction(function () use ($restitution, $actor, $payoutReference, $payoutChannel): WalletRestitution {
            $restitution->update([
                'status' => RestitutionStatus::Paid,
                'paid_at' => now(),
                'payout_reference' => $payoutReference,
                'payout_channel' => $payoutChannel,
            ]);

            $this->ledger->debit(
                $restitution->wallet,
                WalletBalanceType::Cash,
                (int) $restitution->amount,
                WalletTransactionReason::Restitution,
                'restitution:'.$restitution->uuid,
                ['payout_reference' => $payoutReference, 'actor_uuid' => $actor->uuid],
                $restitution,
                $actor,
            );

            activity('wallet')
                ->performedOn($restitution)
                ->causedBy($actor)
                ->event('wallet.restitution.paid')
                ->withProperties(['amount' => (int) $restitution->amount, 'payout_reference' => $payoutReference])
                ->log('wallet.restitution.paid');

            return $restitution->fresh();
        });

        $this->notifications->restitutionPaid($restitution->wallet, (int) $restitution->amount, $payoutReference);

        return $restitution;
    }
}
