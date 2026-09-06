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
            'itinerary_quote_uuid' => ['required', 'uuid', 'exists:itinerary_quotes,uuid'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'passengers' => ['required', 'array', 'min:1', 'max:10'],
            'passengers.*.passenger_index' => ['required', 'integer', 'min:0', 'max:9', 'distinct'],
            'passengers.*.seat_assignments' => ['required', 'array', 'min:1', 'max:6'],
            'passengers.*.seat_assignments.*.leg_order' => ['required', 'integer', 'min:1', 'max:6'],
            'passengers.*.seat_assignments.*.direction' => ['nullable', 'in:outbound,inbound'],
            'passengers.*.seat_assignments.*.seat_uuid' => ['required', 'uuid', 'exists:seats,uuid'],
            'trip_type' => ['nullable', 'in:one_way,round_trip'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
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
     * @return array{itinerary_quote_uuid: string, idempotency_key: string, payer: array<string, mixed>|null, passengers: list<array<string, mixed>>}
     */
    public function holdData(): array
    {
        return [
            'itinerary_quote_uuid' => $this->string('itinerary_quote_uuid')->value(),
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

    /**
     * @return array<string, string|null>
     */
    public function saleQuery(?string $reservationUuid = null): array
    {
        return array_filter([
            'date' => $this->string('date')->value(),
            'from' => $this->string('from')->trim()->value() ?: null,
            'to' => $this->string('to')->trim()->value() ?: null,
            'itinerary_quote_uuid' => $this->string('itinerary_quote_uuid')->value(),
            'reservation_uuid' => $reservationUuid,
            'trip_type' => $this->string('trip_type')->value() ?: null,
            'return_date' => $this->string('return_date')->value() ?: null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
