<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

use App\Http\Requests\JsonValidationRequest;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\Settings\Settings\OperationsSettings;

/** @phpstan-type CompanyPortalLeg array{departure_uuid: string, origin_stop_uuid: string, destination_stop_uuid: string} */
class CompanyPortalQuoteRequest extends JsonValidationRequest
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
        $maxLegs = app(OperationsSettings::class)->maxLegs;

        return [
            'outbound_legs' => ['required', 'array', 'min:1', 'max:'.$maxLegs],
            'outbound_legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'outbound_legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'outbound_legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'inbound_legs' => ['nullable', 'array', 'max:'.$maxLegs],
            'inbound_legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'inbound_legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'inbound_legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:'.GroupBookingSetting::current()->maximum_passengers],
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
            'trip_type' => ['nullable', 'in:one_way,round_trip'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array{passenger_count: int, outbound_legs: list<CompanyPortalLeg>, inbound_legs: list<CompanyPortalLeg>} */
    public function quoteData(): array
    {
        return [
            'passenger_count' => $this->integer('passenger_count'),
            'outbound_legs' => array_values($this->input('outbound_legs', [])),
            'inbound_legs' => array_values($this->input('inbound_legs', [])),
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
