<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItineraryHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'itinerary_quote_uuid' => ['required', 'uuid', 'exists:itinerary_quotes,uuid'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'passengers' => ['required', 'array', 'min:1', 'max:10'],
            'passengers.*.passenger_index' => ['required', 'integer', 'min:0', 'max:9', 'distinct'],
            'passengers.*.seat_assignments' => ['required', 'array', 'min:1', 'max:3'],
            'passengers.*.seat_assignments.*.leg_order' => ['required', 'integer', 'min:1', 'max:3', 'distinct'],
            'passengers.*.seat_assignments.*.seat_uuid' => ['required', 'uuid', 'exists:seats,uuid'],
        ];
    }

    /**
     * @return list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}>
     */
    public function seatAssignments(): array
    {
        /** @var list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}> $passengers */
        $passengers = $this->input('passengers', []);

        return $passengers;
    }
}
