<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'service_class_uuid' => ['nullable', 'uuid', 'exists:service_classes,uuid'],
            'passenger_count' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * @return array{
     *     departure_uuid: string,
     *     origin_stop_uuid: string,
     *     destination_stop_uuid: string,
     *     service_class_uuid: string|null,
     *     passenger_count: int
     * }
     */
    public function quoteData(): array
    {
        return [
            'departure_uuid' => $this->string('departure_uuid')->value(),
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
            'service_class_uuid' => $this->string('service_class_uuid')->trim()->value() ?: null,
            'passenger_count' => $this->integer('passenger_count', 1),
        ];
    }
}
