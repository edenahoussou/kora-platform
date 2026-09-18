<?php

namespace App\Modules\Expenses\Http\Requests;

use App\Modules\Expenses\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Expense::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_uuid' => ['required', 'uuid', Rule::exists('expense_categories', 'uuid')->where('is_active', true)],
            'ledger_uuid' => ['nullable', 'uuid'],
            'payment_channel' => ['required', Rule::in(['cash', 'mtn_momo', 'moov_flooz', 'celtiis_cash'])],
            'amount' => ['required', 'integer', 'min:1'],
            'payee_label' => ['required', 'string', 'max:160'],
            'payee_reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['required', 'date'],
            'evidence' => ['required', 'file', File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf'])->max(5120)],
        ];
    }

    /** @return array<string, mixed> */
    public function expenseData(): array
    {
        return [
            'category_uuid' => (string) $this->validated('category_uuid'),
            'ledger_uuid' => $this->validated('ledger_uuid'),
            'payment_channel' => (string) $this->validated('payment_channel'),
            'amount' => (int) $this->validated('amount'),
            'payee_label' => (string) $this->validated('payee_label'),
            'payee_reference' => $this->validated('payee_reference'),
            'description' => $this->validated('description'),
            'occurred_at' => $this->date('occurred_at'),
            'evidence' => $this->file('evidence'),
        ];
    }
}
