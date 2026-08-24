<?php

namespace App\Modules\Booking\Http\Requests;

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
            'departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:10'],
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array{departure_uuid: string, origin_stop_uuid: string, destination_stop_uuid: string, passenger_count: int}
     */
    public function quoteData(): array
    {
        return [
            'departure_uuid' => $this->string('departure_uuid')->value(),
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
            'passenger_count' => $this->integer('passenger_count'),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function saleQuery(?string $quoteUuid = null): array
    {
        return array_filter([
            'date' => $this->string('date')->value(),
            'from' => $this->string('from')->trim()->value() ?: null,
            'to' => $this->string('to')->trim()->value() ?: null,
            'departure_uuid' => $this->string('departure_uuid')->value(),
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
            'quote_uuid' => $quoteUuid,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
