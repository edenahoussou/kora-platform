<?php

namespace App\Modules\Fiscalization\Http\Requests;

use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListFiscalInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FiscalInvoice::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['all', ...FiscalInvoiceStatus::values()])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{q: string, status: string, from: string, to: string} */
    public function filters(): array
    {
        return [
            'q' => trim((string) $this->validated('q', '')),
            'status' => $this->validated('status') ?: 'all',
            'from' => (string) ($this->validated('from') ?? ''),
            'to' => (string) ($this->validated('to') ?? ''),
        ];
    }
}
