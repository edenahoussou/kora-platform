<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Models\GroupBookingSetting;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'payer' => ['sometimes', 'array'],
            'payer.type' => ['required_with:payer', Rule::in(['personal', 'company'])],
            'payer.user_uuid' => ['nullable', 'uuid'],
            'payer.company_account_uuid' => ['required_if:payer.type,company', 'uuid', 'exists:company_accounts,uuid'],
            'passengers' => ['required', 'array', 'min:1', 'max:'.GroupBookingSetting::current()->maximum_passengers],
            'passengers.*.passenger_index' => ['required', 'integer', 'min:0', 'max:'.(GroupBookingSetting::current()->maximum_passengers - 1), 'distinct'],
            'passengers.*.seat_assignments' => ['required', 'array', 'min:1', 'max:6'],
            'passengers.*.seat_assignments.*.leg_order' => ['required', 'integer', 'min:1', 'max:6'],
            'passengers.*.seat_assignments.*.seat_uuid' => ['required', 'uuid', 'exists:seats,uuid'],
        ];
    }

    /**
     * Validates that leg_order values are distinct within each passenger's
     * seat_assignments (not globally across all passengers).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $passengers = $this->input('passengers', []);

            foreach ($passengers as $pIndex => $passenger) {
                /** @var array<int, array<string, mixed>> $seatAssignments */
                $seatAssignments = is_array($passenger) ? ($passenger['seat_assignments'] ?? []) : [];
                $legOrders = collect($seatAssignments)
                    ->pluck('leg_order')
                    ->map(fn ($order): int => (int) $order)
                    ->all();

                if (count($legOrders) !== count(array_unique($legOrders))) {
                    $validator->errors()->add(
                        "passengers.{$pIndex}.seat_assignments",
                        'Chaque passager doit avoir un siège sur des jambes distinctes.'
                    );
                }
            }
        });
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

    /** @return array<string, mixed> */
    public function payer(): array
    {
        return $this->input('payer', ['type' => 'personal']);
    }
}
