<?php

namespace App\Modules\Fiscalization\Http\Requests;

use App\Modules\Fiscalization\Models\FiscalInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('fiscalInvoice');

        return $invoice instanceof FiscalInvoice
            && $this->user()?->can('credit', $invoice) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['full', 'partial'])],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
            'lines' => [Rule::requiredIf($this->string('mode')->value() === 'partial'), 'array', 'min:1'],
            'lines.*.key' => ['required', 'string', 'regex:/^line-[0-9]+$/'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
