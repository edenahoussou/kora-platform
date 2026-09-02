<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePassengersRequest extends FormRequest
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
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.first_name' => ['required', 'string', 'max:120'],
            'passengers.*.last_name' => ['required', 'string', 'max:120'],
            'passengers.*.phone' => [Rule::requiredIf(function (): bool {
                $reservation = $this->route('reservation');

                return $reservation instanceof Reservation
                    && ($reservation->getRawOriginal('purchase_type') === 'group'
                        || $reservation->getRawOriginal('payer_type') === 'company');
            }), 'nullable', 'string', 'max:32'],
            'passengers.*.email' => ['nullable', 'email', 'max:255'],
            'passengers.*.document_type' => ['nullable', 'string', 'in:national_id,biometric_card,passport,consular_card'],
            'passengers.*.document_number' => ['nullable', 'string', 'max:64'],
            'passengers.*.nationality' => ['nullable', 'string', 'size:2'],
            'passengers.*.birth_date' => ['nullable', 'date_format:Y-m-d'],
            'passengers.*.passenger_index' => ['nullable', 'integer', 'min:0', 'max:9'],
            'passengers.*.seat_uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return list<array{
     *     first_name: string,
     *     last_name: string,
     *     phone?: string|null,
     *     email?: string|null,
     *     document_type?: string|null,
     *     document_number?: string|null,
     *     nationality?: string|null,
     *     birth_date?: string|null,
     *     passenger_index?: int|null,
     *     seat_uuid?: string|null
     * }>
     */
    public function passengersData(): array
    {
        /** @var list<array<string, mixed>> $passengers */
        $passengers = $this->input('passengers', []);

        return array_map(function (array $p): array {
            return [
                'first_name' => (string) ($p['first_name'] ?? ''),
                'last_name' => (string) ($p['last_name'] ?? ''),
                'phone' => isset($p['phone']) ? (string) $p['phone'] : null,
                'email' => isset($p['email']) ? (string) $p['email'] : null,
                'document_type' => isset($p['document_type']) ? (string) $p['document_type'] : null,
                'document_number' => isset($p['document_number']) ? (string) $p['document_number'] : null,
                'nationality' => isset($p['nationality']) ? (string) $p['nationality'] : null,
                'birth_date' => isset($p['birth_date']) ? (string) $p['birth_date'] : null,
                'passenger_index' => isset($p['passenger_index']) ? (int) $p['passenger_index'] : null,
                'seat_uuid' => isset($p['seat_uuid']) ? (string) $p['seat_uuid'] : null,
            ];
        }, $passengers);
    }
}
