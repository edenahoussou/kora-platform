<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

use App\Http\Requests\JsonValidationRequest;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\Settings\Settings\OperationsSettings;

class CompanyPortalHoldRequest extends JsonValidationRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('companyAccount') instanceof CompanyAccount;
    }

    protected function validationRoute(): string
    {
        return 'company-portal.sale.index';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $maximumPassengers = GroupBookingSetting::current()->maximum_passengers;
        $maximumLegOrder = app(OperationsSettings::class)->maxLegs * 2;

        return [
            'itinerary_quote_uuid' => ['required', 'uuid', 'exists:itinerary_quotes,uuid'],
            'idempotency_key' => ['required', 'uuid'],
            'passengers' => ['required', 'array', 'min:1', 'max:'.$maximumPassengers],
            'passengers.*.passenger_index' => ['required', 'integer', 'min:0', 'max:'.($maximumPassengers - 1), 'distinct'],
            'passengers.*.seat_assignments' => ['required', 'array', 'min:1', 'max:'.$maximumLegOrder],
            'passengers.*.seat_assignments.*.leg_order' => ['required', 'integer', 'min:1', 'max:'.$maximumLegOrder],
            'passengers.*.seat_assignments.*.direction' => ['nullable', 'in:outbound,inbound'],
            'passengers.*.seat_assignments.*.seat_uuid' => ['required', 'uuid', 'exists:seats,uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
            'trip_type' => ['nullable', 'in:one_way,round_trip'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return list<array{passenger_index: int, seat_assignments: list<array{leg_order: int, seat_uuid: string}>}> */
    public function seatAssignments(): array
    {
        return array_values(array_map(static fn (array $passenger): array => [
            'passenger_index' => (int) $passenger['passenger_index'],
            'seat_assignments' => array_values(array_map(static fn (array $seat): array => [
                'leg_order' => (int) $seat['leg_order'],
                'seat_uuid' => (string) $seat['seat_uuid'],
            ], $passenger['seat_assignments'])),
        ], $this->input('passengers', [])));
    }

    /** @return array<string, string> */
    public function saleQuery(string $reservationUuid): array
    {
        return array_filter([
            'date' => $this->string('date')->value(),
            'from' => $this->string('from')->trim()->value(),
            'to' => $this->string('to')->trim()->value(),
            'trip_type' => $this->string('trip_type')->value(),
            'return_date' => $this->string('return_date')->value(),
            'reservation_uuid' => $reservationUuid,
        ], static fn (string $value): bool => $value !== '');
    }
}
