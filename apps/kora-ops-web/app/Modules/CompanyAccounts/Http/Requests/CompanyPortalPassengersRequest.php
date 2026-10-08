<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

use App\Http\Requests\JsonValidationRequest;
use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use Illuminate\Validation\Rule;

class CompanyPortalPassengersRequest extends JsonValidationRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('companyAccount') instanceof CompanyAccount;
    }

    protected function validationRoute(): string
    {
        return 'company-portal.sale.index';
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $maximumPassengers = GroupBookingSetting::current()->maximum_passengers;

        return [
            'passengers' => ['required', 'array', 'min:1', 'max:'.$maximumPassengers],
            'passengers.*.traveler_uuid' => ['required', 'uuid', 'distinct', 'exists:company_travelers,uuid'],
            'passengers.*.passenger_index' => ['required', 'integer', 'min:0', 'max:'.($maximumPassengers - 1), 'distinct'],
            'passengers.*.document_type' => ['nullable', 'required_with:passengers.*.document_number', Rule::enum(PassengerDocumentType::class)],
            'passengers.*.document_number' => ['nullable', 'required_with:passengers.*.document_type', 'string', 'max:64'],
            'passengers.*.nationality' => ['nullable', 'string', 'size:2'],
            'passengers.*.birth_date' => ['nullable', 'date_format:Y-m-d'],
            'passengers.*.yellow_fever_vaccinated' => ['nullable', 'boolean'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function passengersData(): array
    {
        return array_values($this->validated('passengers'));
    }
}
