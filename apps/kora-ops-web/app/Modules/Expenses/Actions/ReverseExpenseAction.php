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

class ReverseExpenseAction
{
    public function execute(Expense $expense, User $actor, string $reason): Expense
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Le motif de la contrepassation est obligatoire.']);
        }

        return DB::transaction(function () use ($expense, $actor, $reason): Expense {
            $locked = Expense::query()->lockForUpdate()->whereKey($expense->getKey())->firstOrFail();
            if ($locked->status !== ExpenseStatus::Approved) {
                throw ValidationException::withMessages(['expense' => 'Seule une dépense approuvée peut être contrepassée.']);
            }

            $movement = $locked->cashMovement()->first();
            if ($movement !== null) {
                $ledger = CashLedger::query()->lockForUpdate()->findOrFail($movement->cash_ledger_id);
                $ledger->increment('expected_balance', $movement->amount);
                CashMovement::query()->create([
                    'cash_session_id' => $movement->cash_session_id,
                    'cash_ledger_id' => $movement->cash_ledger_id,
                    'cash_register_id' => $movement->cash_register_id,
                    'expense_id' => null,
                    'type' => 'adjustment',
                    'direction' => CashMovementDirection::In,
                    'status' => CashMovementStatus::Approved,
                    'amount' => $movement->amount,
                    'currency' => $movement->currency,
                    'description' => $reason,
                    'reference' => $locked->reference,
                    'created_by' => $actor->getKey(),
                    'approved_by' => $actor->getKey(),
                    'approved_at' => now(),
                    'occurred_at' => now(),
                ]);
            }

            $locked->update(['status' => ExpenseStatus::Reversed, 'reversed_at' => now(), 'reversal_reason' => $reason]);
            activity('expenses')->performedOn($locked)->causedBy($actor)->withProperties(['reason' => $reason])->log('expense_reversed');

            return $locked;
        }, attempts: 3);
    }
}
