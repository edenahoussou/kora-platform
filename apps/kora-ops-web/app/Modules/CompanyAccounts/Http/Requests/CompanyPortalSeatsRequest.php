<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

use App\Http\Requests\JsonValidationRequest;
use App\Modules\CompanyAccounts\Models\CompanyAccount;

class CompanyPortalSeatsRequest extends JsonValidationRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('companyAccount') instanceof CompanyAccount;
    }

    protected function validationRoute(): string
    {
        return 'company-portal.sale.index';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'itinerary_quote_uuid' => ['required', 'uuid', 'exists:itinerary_quotes,uuid'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
            'trip_type' => ['nullable', 'in:one_way,round_trip'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function saleQuery(string $itineraryQuoteUuid): array
    {
        return array_filter([
            'date' => $this->string('date')->value(),
            'from' => $this->string('from')->trim()->value(),
            'to' => $this->string('to')->trim()->value(),
            'trip_type' => $this->string('trip_type')->value(),
            'return_date' => $this->string('return_date')->value(),
            'itinerary_quote_uuid' => $itineraryQuoteUuid,
        ], static fn (string $value): bool => $value !== '');
    }
}
