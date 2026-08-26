<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveCashVarianceAction
{
    public function execute(CashSession $session, User $approver): CashSession
    {
        return DB::transaction(function () use ($session, $approver): CashSession {
            $lockedSession = CashSession::query()
                ->with('ledgers')
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->status !== CashSessionStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Cette caisse ne contient pas de clôture à approuver.',
                ]);
            }

            $lockedSession->ledgers()->update([
                'status' => CashLedgerStatus::Closed,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
            ]);
            $lockedSession->update([
                'status' => CashSessionStatus::Closed,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
                'closed_at' => now(),
            ]);

            activity('cash')
                ->performedOn($lockedSession)
                ->causedBy($approver)
                ->event('variance_approved')
                ->withProperties(['cash_session_uuid' => $lockedSession->uuid])
                ->log('Écart de caisse approuvé');

            return $lockedSession->fresh('ledgers.agencyPaymentAccount');
        }, attempts: 3);
    }
}
