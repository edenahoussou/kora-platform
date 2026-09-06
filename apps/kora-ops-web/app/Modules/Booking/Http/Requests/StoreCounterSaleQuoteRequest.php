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
            'outbound_legs' => ['required', 'array', 'min:1', 'max:3'],
            'outbound_legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'outbound_legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'outbound_legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'inbound_legs' => ['nullable', 'array', 'max:3'],
            'inbound_legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'inbound_legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'inbound_legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:'.GroupBookingSetting::current()->maximum_passengers],
            'trip_type' => ['nullable', 'in:one_way,round_trip'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
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
            'passenger_count' => $this->integer('passenger_count'),
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
            'itinerary_quote_uuid' => $itineraryQuoteUuid,
            'trip_type' => $this->string('trip_type')->value() ?: null,
            'return_date' => $this->string('return_date')->value() ?: null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
