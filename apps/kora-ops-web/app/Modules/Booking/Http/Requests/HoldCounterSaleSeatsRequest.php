<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'departure_uuid' => ['required_without_all:itinerary_quote_uuid,journey_quote_uuid', 'nullable', 'uuid', 'exists:departures,uuid'],
            'origin_stop_uuid' => ['required_without_all:itinerary_quote_uuid,journey_quote_uuid', 'nullable', 'uuid', 'exists:stops,uuid'],
            'destination_stop_uuid' => ['required_without_all:itinerary_quote_uuid,journey_quote_uuid', 'nullable', 'uuid', 'exists:stops,uuid'],
            'price_quote_uuid' => ['required_without_all:itinerary_quote_uuid,journey_quote_uuid', 'nullable', 'uuid', 'exists:price_quotes,uuid'],
            'itinerary_quote_uuid' => ['nullable', 'uuid', 'exists:itinerary_quotes,uuid'],
            'journey_quote_uuid' => ['nullable', 'uuid', 'exists:journey_quotes,uuid'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'seats' => ['required_without:passengers', 'array', 'min:1', 'max:10'],
            'seats.*.seat_uuid' => ['required', 'uuid', 'exists:seats,uuid'],
            'passengers' => ['required_with:itinerary_quote_uuid,journey_quote_uuid', 'array', 'min:1', 'max:10'],
            'passengers.*.passenger_index' => ['required', 'integer', 'min:0', 'max:9', 'distinct'],
            'passengers.*.seat_assignments' => ['required', 'array', 'min:1', 'max:3'],
            'passengers.*.seat_assignments.*.leg_order' => ['required', 'integer', 'min:1', 'max:3', 'distinct'],
            'passengers.*.seat_assignments.*.direction' => ['nullable', 'in:outbound,inbound'],
            'passengers.*.seat_assignments.*.seat_uuid' => ['required', 'uuid', 'exists:seats,uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
            'payer' => ['sometimes', 'array'],
            'payer.type' => ['required_with:payer', Rule::in(['personal', 'company'])],
            'payer.phone' => ['nullable', 'string', 'max:32'],
            'payer.name' => ['nullable', 'string', 'max:120'],
            'payer.company_account_uuid' => ['required_if:payer.type,company', 'uuid', 'exists:company_accounts,uuid'],
        ];
    }

    /**
     * @return array{departure_uuid: string, origin_stop_uuid: string, destination_stop_uuid: string, price_quote_uuid: string, itinerary_quote_uuid: string|null, journey_quote_uuid: string|null, seat_uuids: list<string>, idempotency_key: string, payer: array<string, mixed>|null, passengers: list<array<string, mixed>>}
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
            'itinerary_quote_uuid' => $this->string('itinerary_quote_uuid')->value() ?: null,
            'journey_quote_uuid' => $this->string('journey_quote_uuid')->value() ?: null,
            'seat_uuids' => array_values(array_unique(array_column($seats, 'seat_uuid'))),
            'idempotency_key' => $this->string('idempotency_key')->trim()->value() ?: Str::uuid()->toString(),
            'payer' => $this->input('payer'),
            'passengers' => $this->input('passengers', []),
        ];
    }

    /** @return list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}> */
    public function itinerarySeatAssignments(): array
    {
        return array_values(array_map(static fn (array $assignment): array => [
            'passenger_index' => (int) $assignment['passenger_index'],
            'seat_assignments' => array_values(array_map(static fn (array $seat): array => [
                'leg_order' => (int) $seat['leg_order'],
                'seat_uuid' => (string) $seat['seat_uuid'],
            ], $assignment['seat_assignments'])),
        ], $this->input('passengers', [])));
    }

    /** @return list<array{passenger_index: int, seat_assignments: list<array{direction: string, leg_order: int, seat_uuid: string}>}> */
    public function journeySeatAssignments(): array
    {
        return array_values(array_map(static fn (array $assignment): array => [
            'passenger_index' => (int) $assignment['passenger_index'],
            'seat_assignments' => array_values(array_map(static fn (array $seat): array => [
                'direction' => (string) ($seat['direction'] ?? 'outbound'),
                'leg_order' => (int) $seat['leg_order'],
                'seat_uuid' => (string) $seat['seat_uuid'],
            ], $assignment['seat_assignments'])),
        ], $this->input('passengers', [])));
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
            'itinerary_quote_uuid' => $this->string('itinerary_quote_uuid')->value(),
            'reservation_uuid' => $reservationUuid,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
