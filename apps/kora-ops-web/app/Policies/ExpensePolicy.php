<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Expenses\Models\Expense;
use App\Policies\Concerns\AuthorizesPermissions;

class ExpensePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'expenses.view-own')
            || $this->allows($user, 'expenses.view-agency')
            || $this->allows($user, 'expenses.view-network');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->inScope($user, $expense);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'expenses.create')
            && ($user->agency_id !== null || $this->allows($user, 'expenses.view-network'));
    }

    public function attachCostCenter(User $user): bool
    {
        return $this->allows($user, 'expenses.view-network');
    }

    public function approve(User $user, Expense $expense): bool
    {
        if ($user->can('expenses.approve-network')) {
            return true;
        }

        return $user->agency_id !== null
            && $user->agency_id === $expense->agency_id
            && $this->allows($user, 'expenses.approve-agency');
    }

    public function reject(User $user, Expense $expense): bool
    {
        return $this->approve($user, $expense);
    }

    public function reverse(User $user, Expense $expense): bool
    {
        return $this->inScope($user, $expense) && $this->allows($user, 'expenses.reverse');
    }

    public function export(User $user): bool
    {
        return $this->allows($user, 'expenses.view-agency')
            || $this->allows($user, 'expenses.view-network');
    }

    private function inScope(User $user, Expense $expense): bool
    {
        if ($user->can('expenses.view-network')) {
            return true;
        }

        if ($user->agency_id !== null && $user->agency_id === $expense->agency_id && $user->can('expenses.view-agency')) {
            return true;
        }

        return $user->can('expenses.view-own') && $expense->created_by === $user->getKey();
    }
}
