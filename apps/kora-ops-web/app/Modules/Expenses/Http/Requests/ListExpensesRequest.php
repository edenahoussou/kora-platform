<?php

namespace App\Modules\Expenses\Http\Requests;

use App\Modules\Expenses\Data\ExpensePaymentChannel;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListExpensesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Expense::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['all', ...ExpenseStatus::values()])],
            'channel' => ['nullable', Rule::in(['all', ...ExpensePaymentChannel::values()])],
            'category' => ['nullable', 'uuid'],
            'agency' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{q: string, status: string, channel: string, category: string, agency: string, from: string, to: string} */
    public function filters(): array
    {
        return [
            'q' => trim((string) $this->validated('q', '')),
            'status' => $this->validated('status') ?: 'all',
            'channel' => $this->validated('channel') ?: 'all',
            'category' => (string) ($this->validated('category') ?? ''),
            'agency' => (string) ($this->validated('agency') ?? ''),
            'from' => (string) ($this->validated('from') ?? ''),
            'to' => (string) ($this->validated('to') ?? ''),
        ];
    }
}
