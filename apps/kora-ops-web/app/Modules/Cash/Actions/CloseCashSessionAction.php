<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloseCashSessionAction
{
    /**
     * @param  list<array{uuid: string, actual_balance: int, closing_comment?: string|null, evidence: UploadedFile, evidence_captured_at?: string}>  $ledgers
     */
    public function execute(CashSession $session, User $agent, array $ledgers): CashSession
    {
        return DB::transaction(function () use ($session, $agent, $ledgers): CashSession {
            $lockedSession = CashSession::query()
                ->with('ledgers')
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->agent_id !== $agent->getKey() || $lockedSession->agency_id !== $agent->agency_id) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Cette caisse n’appartient pas à l’agent connecté.',
                ]);
            }

            if ($lockedSession->status !== CashSessionStatus::Open) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Cette caisse ne peut plus être clôturée.',
                ]);
            }

            if (CashMovement::query()
                ->where('cash_session_id', $lockedSession->getKey())
                ->where('status', CashMovementStatus::Pending->value)
                ->exists()) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Validez ou rejetez les mouvements en attente avant la clôture.',
                ]);
            }

            $submitted = collect($ledgers)->keyBy('uuid');
            if ($submitted->keys()->sort()->values()->all() !== $lockedSession->ledgers->pluck('uuid')->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'ledgers' => 'Rapprochez chaque sous-journal avant de clôturer la caisse.',
                ]);
            }

            $hasVariance = false;
            foreach ($lockedSession->ledgers as $ledger) {
                /** @var array{uuid: string, actual_balance: int, closing_comment?: string|null, evidence: UploadedFile, evidence_captured_at?: string} $data */
                $data = $submitted->get($ledger->uuid);
                $expectedBalance = $this->expectedBalance($ledger);
                $actualBalance = (int) $data['actual_balance'];
                $variance = $actualBalance - $expectedBalance;
                $hasVariance = $hasVariance || $variance !== 0;

                if ($variance !== 0 && trim((string) ($data['closing_comment'] ?? '')) === '') {
                    throw ValidationException::withMessages([
                        'ledgers' => 'Un commentaire est obligatoire pour chaque écart constaté.',
                    ]);
                }

                $ledger->update([
                    'expected_balance' => $expectedBalance,
                    'actual_balance' => $actualBalance,
                    'variance' => $variance,
                    'status' => $variance === 0 ? CashLedgerStatus::Closed : CashLedgerStatus::PendingApproval,
                    'closed_by' => $agent->getKey(),
                    'closed_at' => now(),
                    'closing_comment' => $data['closing_comment'] ?? null,
                ]);

                $ledger->addMedia($data['evidence'])
                    ->withCustomProperties([
                        'evidence_type' => 'closing_balance',
                        'ledger_uuid' => $ledger->uuid,
                        'evidence_source' => 'live_camera',
                        'captured_by' => $agent->uuid,
                        'captured_at' => now()->toISOString(),
                        'captured_at_client' => $data['evidence_captured_at'] ?? null,
                    ])
                    ->toMediaCollection('closing-evidence');
            }

            $lockedSession->update([
                'status' => $hasVariance ? CashSessionStatus::PendingApproval : CashSessionStatus::Closed,
                'submitted_at' => $hasVariance ? now() : null,
                'closed_at' => $hasVariance ? null : now(),
                'closing_comment' => collect($ledgers)->pluck('closing_comment')->filter()->implode(' | ') ?: null,
            ]);

            activity('cash')
                ->performedOn($lockedSession)
                ->causedBy($agent)
                ->event($hasVariance ? 'session_submitted_with_variance' : 'session_closed')
                ->withProperties(['cash_session_uuid' => $lockedSession->uuid])
                ->log($hasVariance ? 'Clôture de caisse soumise avec écart' : 'Session de caisse clôturée');

            return $lockedSession->fresh('ledgers.agencyPaymentAccount');
        }, attempts: 3);
    }

    private function expectedBalance(CashLedger $ledger): int
    {
        $postedStatuses = [
            CashMovementStatus::Posted->value,
            CashMovementStatus::Approved->value,
        ];
        $incoming = (int) $ledger->movements()->whereIn('status', $postedStatuses)->where('direction', CashMovementDirection::In->value)->sum('amount');
        $outgoing = (int) $ledger->movements()->whereIn('status', $postedStatuses)->where('direction', CashMovementDirection::Out->value)->sum('amount');

        return (int) $ledger->opening_balance + $incoming - $outgoing;
    }
}
