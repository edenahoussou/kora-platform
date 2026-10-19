<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Notifications\Services\OpsNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ForceCloseCashSessionAction
{
    public function __construct(private OpsNotifier $notifier) {}

    /**
     * Clôture d'office une session restée ouverte, sans comptage ni preuve :
     * les ledgers restent sans solde constaté (« non contrôlé ») et les
     * mouvements en attente sont rejetés.
     */
    public function execute(CashSession $session, User $manager, string $reason): CashSession
    {
        $closed = DB::transaction(function () use ($session, $manager, $reason): CashSession {
            $lockedSession = CashSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($manager->agency_id === null || $lockedSession->agency_id !== $manager->agency_id) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Cette caisse n’appartient pas à votre agence.',
                ]);
            }

            if ($lockedSession->status !== CashSessionStatus::Open) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Seule une session encore ouverte peut être clôturée de force.',
                ]);
            }

            $lockedSession->movements()
                ->where('status', CashMovementStatus::Pending->value)
                ->update([
                    'status' => CashMovementStatus::Rejected->value,
                    'approved_by' => $manager->getKey(),
                    'approved_at' => now(),
                    'rejected_at' => now(),
                    'rejection_reason' => 'Session clôturée de force',
                ]);

            $lockedSession->ledgers()->update([
                'status' => CashLedgerStatus::Closed,
                'closed_by' => $manager->getKey(),
                'closed_at' => now(),
            ]);

            $lockedSession->update([
                'status' => CashSessionStatus::Closed,
                'closed_at' => now(),
                'closing_comment' => $reason,
                'force_closed_by' => $manager->getKey(),
                'force_closed_at' => now(),
            ]);

            activity('cash')
                ->performedOn($lockedSession)
                ->causedBy($manager)
                ->event('session_force_closed')
                ->withProperties(['cash_session_uuid' => $lockedSession->uuid, 'reason' => $reason])
                ->log('Session de caisse clôturée de force');

            return $lockedSession->fresh(['ledgers.agencyPaymentAccount', 'agent', 'agency']);
        }, attempts: 3);

        $this->notifier->cashSessionForceClosed($closed, $manager);

        return $closed;
    }
}
