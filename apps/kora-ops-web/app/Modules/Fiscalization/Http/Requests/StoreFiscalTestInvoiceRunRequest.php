<?php

namespace App\Modules\Fiscalization\Http\Requests;

use App\Modules\IdentityAccess\Data\SystemRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFiscalTestInvoiceRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(SystemRole::SystemAdmin->value) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'caseNumber' => ['required', 'integer', Rule::in(range(1, 10))],
            'clientIfu' => ['nullable', 'required_if:caseNumber,5,7,9', 'digits:13'],
        ];
    }
}
