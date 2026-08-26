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

class RecordCashMovementAction
{
    /**
     * @param  array{ledger_uuid: string, amount: int, type: string, destination_label: string, destination_reference?: string|null, description?: string|null, evidence: UploadedFile}  $attributes
     */
    public function execute(User $agent, array $attributes): CashMovement
    {
        return DB::transaction(function () use ($agent, $attributes): CashMovement {
            $session = CashSession::query()
                ->where('agent_id', $agent->getKey())
                ->where('agency_id', $agent->agency_id)
                ->whereDate('business_date', now()->toDateString())
                ->where('status', CashSessionStatus::Open->value)
                ->with('ledgers')
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Ouvrez votre caisse avant d’enregistrer un reversement.',
                ]);
            }

            $ledger = $session->ledgers->firstWhere('uuid', $attributes['ledger_uuid']);
            if (! $ledger instanceof CashLedger) {
                throw ValidationException::withMessages([
                    'ledger_uuid' => 'Le sous-journal sélectionné est invalide.',
                ]);
            }

            if ($ledger->status !== CashLedgerStatus::Open) {
                throw ValidationException::withMessages([
                    'ledger_uuid' => 'Ce sous-journal n’est plus ouvert aux nouveaux mouvements.',
                ]);
            }

            $movement = CashMovement::query()->create([
                'cash_session_id' => $session->getKey(),
                'cash_ledger_id' => $ledger->getKey(),
                'type' => $attributes['type'],
                'direction' => CashMovementDirection::Out,
                'status' => CashMovementStatus::Pending,
                'amount' => (int) $attributes['amount'],
                'currency' => 'XOF',
                'description' => $attributes['description'] ?? null,
                'reference' => $attributes['destination_reference'] ?? null,
                'destination_label' => $attributes['destination_label'],
                'destination_reference' => $attributes['destination_reference'] ?? null,
                'created_by' => $agent->getKey(),
                'occurred_at' => now(),
            ]);

            $movement->addMedia($attributes['evidence'])
                ->withCustomProperties([
                    'evidence_type' => 'transfer',
                    'movement_uuid' => $movement->uuid,
                    'captured_by' => $agent->uuid,
                    'captured_at' => now()->toISOString(),
                ])
                ->toMediaCollection('transfer-evidence');

            activity('cash')
                ->performedOn($movement)
                ->causedBy($agent)
                ->event('movement_created')
                ->withProperties(['movement_uuid' => $movement->uuid, 'status' => 'pending'])
                ->log('Mouvement de reversement créé');

            return $movement->load('cashLedger');
        }, attempts: 3);
    }
}
