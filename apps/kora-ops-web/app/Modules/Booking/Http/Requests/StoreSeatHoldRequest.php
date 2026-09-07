<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'seats.*.seat_uuid' => ['required', 'uuid', 'exists:seats,uuid'],
            'seats.*.passenger_index' => ['nullable', 'integer'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'payer' => ['sometimes', 'array'],
            'payer.type' => ['required_with:payer', Rule::in(['personal', 'company'])],
            'payer.user_uuid' => ['nullable', 'uuid'],
            'payer.company_account_uuid' => ['required_if:payer.type,company', 'uuid', 'exists:company_accounts,uuid'],
        ];
    }

    /**
     * @return array{
     *     departure_uuid: string,
     *     origin_stop_uuid: string,
     *     destination_stop_uuid: string,
     *     price_quote_uuid: string,
     *     seat_uuids: list<string>,
     *     idempotency_key: string,
     *     payer: array<string, mixed>
     * }
     */
    public function holdData(): array
    {
        /** @var array<int, array{seat_uuid: string}> $rawSeats */
        $rawSeats = $this->input('seats', []);
        $seatUuids = array_values(array_unique(array_map(fn (array $item): string => $item['seat_uuid'], $rawSeats)));

        return [
            'departure_uuid' => $this->string('departure_uuid')->value(),
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
            'price_quote_uuid' => $this->string('price_quote_uuid')->value(),
            'seat_uuids' => $seatUuids,
            'idempotency_key' => $this->string('idempotency_key')->trim()->value(),
            'payer' => $this->input('payer', ['type' => 'personal']),
        ];
    }
}
