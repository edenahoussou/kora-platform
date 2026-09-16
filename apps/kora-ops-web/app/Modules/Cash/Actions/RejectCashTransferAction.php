<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashTransferStatus;
use App\Modules\Cash\Models\CashTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectCashTransferAction
{
    public function execute(CashTransfer $transfer, User $approver, ?string $reason = null): CashTransfer
    {
        return DB::transaction(function () use ($transfer, $approver, $reason): CashTransfer {
            $locked = CashTransfer::query()->whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
            if ($approver->agency_id !== $locked->agency_id) {
                throw ValidationException::withMessages(['transfer' => 'Ce transfert n’appartient pas à votre agence.']);
            }
            if ($locked->status !== CashTransferStatus::Pending) {
                throw ValidationException::withMessages(['transfer' => 'Ce transfert a déjà été traité.']);
            }

            $locked->update([
                'status' => CashTransferStatus::Rejected,
                'approved_by' => $approver->getKey(),
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $locked;
        });
    }
}
