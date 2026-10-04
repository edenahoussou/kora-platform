<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyTraveler;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreCompanyTravelerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('companyAccount') instanceof CompanyAccount;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => app(PassengerIdentityResolver::class)
                ->normalizePhone($this->input('phone')),
            'nationality' => $this->filled('nationality')
                ? strtoupper((string) $this->input('nationality'))
                : null,
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32', $this->phoneUniqueRule()],
            'email' => ['nullable', 'email', 'max:255'],
            'document_type' => ['nullable', 'required_with:document_number', Rule::enum(PassengerDocumentType::class)],
            'document_number' => ['nullable', 'required_with:document_type', 'string', 'max:64'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'yellow_fever_vaccinated' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{first_name: string, last_name: string, phone: string, email?: string|null, document_type?: string|null, document_number?: string|null, nationality?: string|null, birth_date?: string|null, yellow_fever_vaccinated?: bool|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'first_name' => (string) $validated['first_name'],
            'last_name' => (string) $validated['last_name'],
            'phone' => (string) $validated['phone'],
            'email' => $validated['email'] ?? null,
            'document_type' => $validated['document_type'] ?? null,
            'document_number' => $validated['document_number'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'yellow_fever_vaccinated' => array_key_exists('yellow_fever_vaccinated', $validated)
                ? (bool) $validated['yellow_fever_vaccinated']
                : null,
        ];
    }

    protected function phoneUniqueRule(): Unique
    {
        $rule = Rule::unique('company_travelers', 'phone')
            ->where('company_account_id', $this->companyAccount()->getKey());

        $traveler = $this->route('traveler');

        return $traveler instanceof CompanyTraveler
            ? $rule->ignore($traveler->getKey())
            : $rule;
    }

    protected function companyAccount(): CompanyAccount
    {
        /** @var CompanyAccount $account */
        $account = $this->attributes->get('companyAccount');

        return $account;
    }
}
