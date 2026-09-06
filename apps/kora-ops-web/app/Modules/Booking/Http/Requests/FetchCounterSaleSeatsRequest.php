<?php

namespace App\Modules\Booking\Http\Requests;

use App\Http\Requests\JsonValidationRequest;
use App\Modules\Booking\Models\Reservation;

class FetchCounterSaleSeatsRequest extends JsonValidationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createCounterSale', Reservation::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'outbound_legs' => ['required', 'array', 'min:1', 'max:3'],
            'outbound_legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'outbound_legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'outbound_legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'inbound_legs' => ['nullable', 'array', 'max:3'],
            'inbound_legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'inbound_legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'inbound_legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'trip_type' => ['nullable', 'in:one_way,round_trip'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array{outbound_legs: list<array{departure_uuid: string, origin_stop_uuid: string, destination_stop_uuid: string}>, inbound_legs: list<array{departure_uuid: string, origin_stop_uuid: string, destination_stop_uuid: string}>}
     */
    public function legsData(): array
    {
        return [
            'outbound_legs' => array_values($this->input('outbound_legs', [])),
            'inbound_legs' => array_values($this->input('inbound_legs', [])),
        ];
    }
}
