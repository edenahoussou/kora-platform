<?php

namespace App\Modules\Expenses\Actions;

use App\Models\User;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectExpenseAction
{
    public function execute(Expense $expense, User $approver, string $reason): Expense
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Le motif du rejet est obligatoire.']);
        }

        return DB::transaction(function () use ($expense, $approver, $reason): Expense {
            $locked = Expense::query()->lockForUpdate()->whereKey($expense->getKey())->firstOrFail();
            if ($locked->status !== ExpenseStatus::Pending) {
                throw ValidationException::withMessages(['expense' => 'Cette dépense n’est plus en attente de validation.']);
            }
            $locked->update(['status' => ExpenseStatus::Rejected, 'approved_by' => $approver->getKey(), 'rejected_at' => now(), 'rejection_reason' => $reason]);
            activity('expenses')->performedOn($locked)->causedBy($approver)->withProperties(['reason' => $reason])->log('expense_rejected');

            return $locked;
        }, attempts: 3);
    }
}
