<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Data\CashTransferStatus;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashTransfer;
use App\Modules\Cash\Services\CashRegisterBalance;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveCashTransferAction
{
    public function __construct(private readonly CashRegisterBalance $balances) {}

    public function execute(CashTransfer $transfer, User $approver): CashTransfer
    {
        return DB::transaction(function () use ($transfer, $approver): CashTransfer {
            $locked = CashTransfer::query()->whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
            if ($approver->agency_id !== $locked->agency_id) {
                throw ValidationException::withMessages(['transfer' => 'Ce transfert n’appartient pas à votre agence.']);
            }
            if ($locked->status !== CashTransferStatus::Pending) {
                throw ValidationException::withMessages(['transfer' => 'Ce transfert a déjà été traité.']);
            }

            $source = $locked->sourceCashRegister()->lockForUpdate()->first();
            $destination = $locked->destinationCashRegister()->lockForUpdate()->first();
            if ($source instanceof CashRegister && $this->balances->available($source) < $locked->amount) {
                throw ValidationException::withMessages(['transfer' => 'Le solde disponible est devenu insuffisant.']);
            }

            $occurredAt = now();
            if ($source instanceof CashRegister) {
                $this->movement($locked, $source, CashMovementDirection::Out, $approver, $occurredAt);
            }
            if ($destination instanceof CashRegister) {
                $this->movement($locked, $destination, CashMovementDirection::In, $approver, $occurredAt);
            }

            $locked->update([
                'status' => CashTransferStatus::Approved,
                'approved_by' => $approver->getKey(),
                'approved_at' => $occurredAt,
                'occurred_at' => $occurredAt,
            ]);

            return $locked->fresh(['movements', 'sourceCashRegister', 'destinationCashRegister']);
        });
    }

    private function movement(CashTransfer $transfer, CashRegister $register, CashMovementDirection $direction, User $approver, DateTimeInterface $occurredAt): CashMovement
    {
        return CashMovement::query()->create([
            'cash_transfer_id' => $transfer->getKey(),
            'cash_register_id' => $register->getKey(),
            'type' => 'cash_transfer',
            'direction' => $direction,
            'status' => CashMovementStatus::Approved,
            'amount' => $transfer->amount,
            'currency' => $transfer->currency,
            'description' => $transfer->description,
            'reference' => $transfer->reference,
            'destination_label' => $transfer->destination_type === 'direction' ? 'KORA Direction' : $transfer->destinationCashRegister()->value('label'),
            'created_by' => $transfer->created_by,
            'approved_by' => $approver->getKey(),
            'approved_at' => $occurredAt,
            'occurred_at' => $occurredAt,
        ]);
    }
}
