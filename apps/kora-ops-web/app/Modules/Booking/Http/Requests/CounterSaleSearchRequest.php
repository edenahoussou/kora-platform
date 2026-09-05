<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Departures\Models\Departure;
use Illuminate\Foundation\Http\FormRequest;

class CounterSaleSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Departure::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
            'departure_uuid' => ['nullable', 'uuid', 'exists:departures,uuid'],
            'origin_stop_uuid' => ['nullable', 'uuid', 'exists:stops,uuid'],
            'destination_stop_uuid' => ['nullable', 'uuid', 'exists:stops,uuid'],
            'quote_uuid' => ['nullable', 'uuid', 'exists:price_quotes,uuid'],
            'itinerary_quote_uuid' => ['nullable', 'uuid', 'exists:itinerary_quotes,uuid'],
            'journey_quote_uuid' => ['nullable', 'uuid', 'exists:journey_quotes,uuid'],
            'trip_type' => ['nullable', 'in:one_way,round_trip'],
            'origin_physical_stop_uuid' => ['nullable', 'uuid', 'exists:physical_stops,uuid'],
            'destination_physical_stop_uuid' => ['nullable', 'uuid', 'exists:physical_stops,uuid'],
            'departure_date' => ['nullable', 'date_format:Y-m-d'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
            'passenger_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'selected_outbound_uuid' => ['nullable', 'uuid'],
            'selected_inbound_uuid' => ['nullable', 'uuid'],
            'reservation_uuid' => ['nullable', 'uuid', 'exists:reservations,uuid'],
        ];
    }

    /**
     * @return array{date: string|null, from: string|null, to: string|null}
     */
    public function departureFilters(): array
    {
        return [
            'date' => $this->string('date')->trim()->value() ?: null,
            'from' => $this->string('from')->trim()->value() ?: null,
            'to' => $this->string('to')->trim()->value() ?: null,
        ];
    }

    /**
     * @return array{departure_uuid: string|null, origin_stop_uuid: string|null, destination_stop_uuid: string|null, quote_uuid: string|null, reservation_uuid: string|null}
     */
    public function selectedContext(): array
    {
        return [
            'departure_uuid' => $this->string('departure_uuid')->trim()->value() ?: null,
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->trim()->value() ?: null,
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->trim()->value() ?: null,
            'quote_uuid' => $this->string('quote_uuid')->trim()->value() ?: null,
            'itinerary_quote_uuid' => $this->string('itinerary_quote_uuid')->trim()->value() ?: null,
            'journey_quote_uuid' => $this->string('journey_quote_uuid')->trim()->value() ?: null,
            'reservation_uuid' => $this->string('reservation_uuid')->trim()->value() ?: null,
        ];
    }

    public function hasExplicitSaleContext(): bool
    {
        foreach ([
            'date',
            'from',
            'to',
            'departure_uuid',
            'origin_stop_uuid',
            'destination_stop_uuid',
            'quote_uuid',
            'itinerary_quote_uuid',
            'reservation_uuid',
            'journey_quote_uuid',
            'trip_type',
            'origin_physical_stop_uuid',
            'destination_physical_stop_uuid',
            'departure_date',
            'return_date',
            'passenger_count',
            'selected_outbound_uuid',
            'selected_inbound_uuid',
        ] as $key) {
            if ($this->filled($key)) {
                return true;
            }
        }

        return false;
    }
}
