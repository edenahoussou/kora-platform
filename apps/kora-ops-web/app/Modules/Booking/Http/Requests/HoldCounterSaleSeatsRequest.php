<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class HoldCounterSaleSeatsRequest extends FormRequest
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
            'departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'price_quote_uuid' => ['required', 'uuid', 'exists:price_quotes,uuid'],
            'seats' => ['required', 'array', 'min:1', 'max:10'],
            'seats.*.seat_uuid' => ['required', 'uuid', 'exists:seats,uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array{departure_uuid: string, origin_stop_uuid: string, destination_stop_uuid: string, price_quote_uuid: string, seat_uuids: list<string>}
     */
    public function holdData(): array
    {
        /** @var array<int, array{seat_uuid: string}> $seats */
        $seats = $this->input('seats', []);

        return [
            'departure_uuid' => $this->string('departure_uuid')->value(),
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
            'price_quote_uuid' => $this->string('price_quote_uuid')->value(),
            'seat_uuids' => array_values(array_unique(array_column($seats, 'seat_uuid'))),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function saleQuery(?string $reservationUuid = null): array
    {
        return array_filter([
            'date' => $this->string('date')->value(),
            'from' => $this->string('from')->trim()->value() ?: null,
            'to' => $this->string('to')->trim()->value() ?: null,
            'departure_uuid' => $this->string('departure_uuid')->value(),
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
            'quote_uuid' => $this->string('price_quote_uuid')->value(),
            'reservation_uuid' => $reservationUuid,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
