<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeatHoldRequest extends FormRequest
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
            'price_quote_uuid' => ['required', 'uuid', 'exists:price_quotes,uuid'],
            'seats' => ['required', 'array', 'min:1'],
            'seats.*.seat_uuid' => ['required', 'uuid', 'distinct', 'exists:seats,uuid'],
            'seats.*.passenger_index' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array{
     *     departure_uuid: string,
     *     origin_stop_uuid: string,
     *     destination_stop_uuid: string,
     *     price_quote_uuid: string,
     *     seat_uuids: list<string>
     * }
     */
    public function holdData(): array
    {
        /** @var array<int, array{seat_uuid: string}> $rawSeats */
        $rawSeats = $this->input('seats', []);
        $seatUuids = array_values(array_map(fn (array $item): string => $item['seat_uuid'], $rawSeats));

        return [
            'departure_uuid' => $this->string('departure_uuid')->value(),
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
            'price_quote_uuid' => $this->string('price_quote_uuid')->value(),
            'seat_uuids' => $seatUuids,
        ];
    }
}
