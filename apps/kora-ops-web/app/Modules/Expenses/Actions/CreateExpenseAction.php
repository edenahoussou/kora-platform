<?php

namespace App\Modules\Expenses\Actions;

use App\Models\User;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateExpenseAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, array $data): Expense
    {
        return DB::transaction(function () use ($actor, $data): Expense {
            $category = ExpenseCategory::query()->where('uuid', $data['category_uuid'])->where('is_active', true)->first();
            if ($category === null) {
                throw ValidationException::withMessages(['category_uuid' => 'La catégorie sélectionnée est inactive.']);
            }

            $ledger = null;
            if (! empty($data['ledger_uuid'])) {
                $ledger = CashLedger::query()->with('cashSession')
                    ->where('uuid', $data['ledger_uuid'])
                    ->whereHas('cashSession', fn ($query) => $query->where('agency_id', $actor->agency_id))
                    ->first();
                if ($ledger === null) {
                    throw ValidationException::withMessages(['ledger_uuid' => 'Le sous-journal sélectionné est invalide.']);
                }
            }

            $expense = Expense::query()->create([
                'agency_id' => $actor->agency_id,
                'expense_category_id' => $category->getKey(),
                'cash_session_id' => $ledger?->cash_session_id,
                'cash_ledger_id' => $ledger?->getKey(),
                'status' => 'pending',
                'payment_channel' => $data['payment_channel'],
                'amount' => $data['amount'],
                'currency' => 'XOF',
                'payee_label' => $data['payee_label'],
                'payee_reference' => $data['payee_reference'] ?? null,
                'description' => $data['description'] ?? null,
                'occurred_at' => $data['occurred_at'],
                'created_by' => $actor->getKey(),
            ]);

            $expense->addMedia($data['evidence'])->toMediaCollection('evidence');
            activity('expenses')->performedOn($expense)->causedBy($actor)->withProperties(['reference' => $expense->reference])->log('expense_created');

            return $expense;
        }, attempts: 3);
    }
}
