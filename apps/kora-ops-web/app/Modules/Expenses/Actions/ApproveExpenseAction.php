<?php

namespace App\Modules\Expenses\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashMovementDirection;
use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveExpenseAction
{
    public function execute(Expense $expense, User $approver): Expense
    {
        return DB::transaction(function () use ($expense, $approver): Expense {
            $locked = Expense::query()->with('cashSession')->whereKey($expense->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== ExpenseStatus::Pending) {
                throw ValidationException::withMessages(['expense' => 'Cette dépense n’est plus en attente de validation.']);
            }

            if ($locked->cash_ledger_id !== null) {
                $ledger = CashLedger::query()->with('cashSession.cashRegister')->lockForUpdate()->findOrFail($locked->cash_ledger_id);
                if ((int) $ledger->expected_balance < (int) $locked->amount) {
                    throw ValidationException::withMessages(['expense' => 'Le solde du sous-journal est insuffisant.']);
                }
                $ledger->decrement('expected_balance', $locked->amount);
                $register = $ledger->cashSession?->cashRegister;
                CashMovement::query()->create([
                    'cash_session_id' => $ledger->cash_session_id,
                    'cash_ledger_id' => $ledger->getKey(),
                    'cash_register_id' => $register?->getKey(),
                    'expense_id' => $locked->getKey(),
                    'type' => 'expense',
                    'direction' => CashMovementDirection::Out,
                    'status' => CashMovementStatus::Approved,
                    'amount' => $locked->amount,
                    'currency' => $locked->currency,
                    'description' => $locked->description,
                    'reference' => $locked->reference,
                    'destination_label' => $locked->payee_label,
                    'destination_reference' => $locked->payee_reference,
                    'created_by' => $locked->created_by,
                    'approved_by' => $approver->getKey(),
                    'approved_at' => now(),
                    'occurred_at' => $locked->occurred_at,
                ]);
            }

            $locked->update(['status' => ExpenseStatus::Approved, 'approved_by' => $approver->getKey(), 'approved_at' => now()]);
            activity('expenses')->performedOn($locked)->causedBy($approver)->withProperties(['reference' => $locked->reference])->log('expense_approved');

            return $locked->fresh(['category', 'agency', 'cashMovement']);
        }, attempts: 3);
    }
}
