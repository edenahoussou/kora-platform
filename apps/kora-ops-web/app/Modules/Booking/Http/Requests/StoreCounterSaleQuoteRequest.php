<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\Booking\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class StoreCounterSaleQuoteRequest extends FormRequest
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
            'departure_uuid' => ['required_without_all:legs,outbound_legs', 'nullable', 'uuid', 'exists:departures,uuid'],
            'origin_stop_uuid' => ['required_without_all:legs,outbound_legs', 'nullable', 'uuid', 'exists:stops,uuid'],
            'destination_stop_uuid' => ['required_without_all:legs,outbound_legs', 'nullable', 'uuid', 'exists:stops,uuid'],
            'legs' => ['required_without_all:departure_uuid,outbound_legs', 'nullable', 'array', 'min:1', 'max:3'],
            'legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'trip_type' => ['nullable', 'in:one_way,round_trip'],
            'origin_physical_stop_uuid' => ['nullable', 'uuid', 'exists:physical_stops,uuid'],
            'destination_physical_stop_uuid' => ['nullable', 'uuid', 'exists:physical_stops,uuid'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
            'selected_outbound_uuid' => ['nullable', 'uuid'],
            'selected_inbound_uuid' => ['nullable', 'uuid'],
            'outbound_legs' => ['nullable', 'array', 'min:1', 'max:3'],
            'inbound_legs' => ['nullable', 'array', 'max:3'],
            'outbound_legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'outbound_legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'outbound_legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'inbound_legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'inbound_legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'inbound_legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:'.GroupBookingSetting::current()->maximum_passengers],
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteData(): array
    {
        return [
            'departure_uuid' => $this->string('departure_uuid')->value(),
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
            'passenger_count' => $this->integer('passenger_count'),
            'legs' => $this->input('legs', []),
            'trip_type' => $this->string('trip_type')->value() ?: 'one_way',
            'origin_physical_stop_uuid' => $this->string('origin_physical_stop_uuid')->value() ?: null,
            'destination_physical_stop_uuid' => $this->string('destination_physical_stop_uuid')->value() ?: null,
            'return_date' => $this->string('return_date')->value() ?: null,
            'selected_outbound_uuid' => $this->string('selected_outbound_uuid')->value() ?: null,
            'selected_inbound_uuid' => $this->string('selected_inbound_uuid')->value() ?: null,
            'outbound_legs' => $this->input('outbound_legs', []),
            'inbound_legs' => $this->input('inbound_legs', []),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function saleQuery(?string $quoteUuid = null, ?string $itineraryQuoteUuid = null): array
    {
        return array_filter([
            'date' => $this->string('date')->value(),
            'from' => $this->string('from')->trim()->value() ?: null,
            'to' => $this->string('to')->trim()->value() ?: null,
            'departure_uuid' => $this->string('departure_uuid')->value(),
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
            'quote_uuid' => $quoteUuid,
            'itinerary_quote_uuid' => $itineraryQuoteUuid,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
