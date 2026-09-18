<?php

namespace App\Modules\Expenses\Http\Requests;

use App\Modules\Expenses\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense && ($this->user()?->can('approve', $expense) || $this->user()?->can('reverse', $expense));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }
}
