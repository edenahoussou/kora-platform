<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveCashMovementAction
{
    public function execute(CashMovement $movement, User $approver): CashMovement
    {
        return DB::transaction(function () use ($movement, $approver): CashMovement {
            $lockedMovement = CashMovement::query()
                ->with('cashLedger')
                ->lockForUpdate()
                ->whereKey($movement->getKey())
                ->firstOrFail();

            if ($lockedMovement->status !== CashMovementStatus::Pending) {
                throw ValidationException::withMessages([
                    'movement' => 'Ce mouvement n’est plus en attente de validation.',
                ]);
            }

            $ledger = $lockedMovement->cashLedger;
            if ($ledger === null) {
                throw ValidationException::withMessages([
                    'movement' => 'Le sous-journal de ce mouvement est introuvable.',
                ]);
            }

            $lockedLedger = CashLedger::query()
                ->whereKey($ledger->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedLedger->expected_balance < $lockedMovement->amount) {
                throw ValidationException::withMessages([
                    'movement' => 'Le solde attendu du sous-journal est insuffisant pour ce reversement.',
                ]);
            }
            $lockedLedger->decrement('expected_balance', $lockedMovement->amount);

            $lockedMovement->update([
                'status' => CashMovementStatus::Approved,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
            ]);

            activity('cash')
                ->performedOn($lockedMovement)
                ->causedBy($approver)
                ->event('movement_approved')
                ->withProperties(['movement_uuid' => $lockedMovement->uuid])
                ->log('Mouvement de reversement approuvé');

            return $lockedMovement->fresh('cashLedger');
        }, attempts: 3);
    }

    public function reject(CashMovement $movement, User $approver, string $reason): CashMovement
    {
        return DB::transaction(function () use ($movement, $approver, $reason): CashMovement {
            $lockedMovement = CashMovement::query()
                ->lockForUpdate()
                ->whereKey($movement->getKey())
                ->firstOrFail();

            if ($lockedMovement->status !== CashMovementStatus::Pending) {
                throw ValidationException::withMessages([
                    'movement' => 'Ce mouvement n’est plus en attente de validation.',
                ]);
            }

            $lockedMovement->update([
                'status' => CashMovementStatus::Rejected,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $lockedMovement->fresh('cashLedger');
        }, attempts: 3);
    }
}
