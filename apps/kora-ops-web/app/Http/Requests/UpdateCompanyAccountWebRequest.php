<?php

namespace App\Http\Requests;

use App\Modules\CompanyAccounts\Data\CompanyAccountType;
use App\Modules\CompanyAccounts\Data\CompanyDiscountType;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCompanyAccountWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company-accounts.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CompanyAccountType::class)],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],
            'ifu' => ['nullable', 'string', 'max:64'],
            'rccm' => ['nullable', 'string', 'max:128'],
            'monthly_credit_limit' => ['required', 'integer', 'min:0'],
            'discount_type' => ['nullable', Rule::enum(CompanyDiscountType::class)],
            'discount_value' => [
                'nullable',
                'required_with:discount_type',
                'numeric',
                'min:0',
                Rule::when($this->input('discount_type') === CompanyDiscountType::Percentage->value, ['max:100']),
            ],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $account = $this->route('companyAccount');

            if (! $account instanceof CompanyAccount) {
                return;
            }

            $confirmedDebits = $account->creditTransactions()
                ->where('period_key', now()->format('Y-m'))
                ->where('type', 'debit')
                ->sum('amount');

            if ((int) $this->integer('monthly_credit_limit') < (int) $confirmedDebits) {
                $validator->errors()->add(
                    'monthly_credit_limit',
                    'Le plafond doit rester au moins égal aux débits confirmés de ce mois ('.number_format((int) $confirmedDebits, 0, ',', ' ').' FCFA).',
                );
            }
        }];
    }
}
