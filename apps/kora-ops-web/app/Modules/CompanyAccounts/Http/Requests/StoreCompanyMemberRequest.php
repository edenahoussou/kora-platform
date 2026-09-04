<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

use App\Modules\CompanyAccounts\Data\CompanyMemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company-accounts.manage') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'uuid', 'exists:users,uuid'],
            'role' => ['required', Rule::enum(CompanyMemberRole::class)],
        ];
    }
}
