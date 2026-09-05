<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'passengers.*.phone' => ['nullable', 'string', 'max:32'],
            'passengers.*.email' => ['nullable', 'email', 'max:255'],
            'passengers.*.document_number' => ['nullable', 'required_with:passengers.*.document_type', 'string', 'max:64'],
            'passengers.*.document_type' => ['nullable', 'required_with:passengers.*.document_number', 'string', 'in:national_id,biometric_card,passport,consular_card'],
            'passengers.*.nationality' => ['nullable', 'string', 'size:2'],
            'passengers.*.birth_date' => ['nullable', 'date_format:Y-m-d'],
            'passengers.*.passenger_index' => ['nullable', 'integer', 'min:0', 'max:9', 'distinct'],
            'passengers.*.seat_uuid' => ['nullable', 'uuid'],
            'passengers.*.yellow_fever_vaccinated' => ['nullable', 'boolean'],
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
     *     seat_uuid?: string|null,
     *     yellow_fever_vaccinated?: bool|null
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
                'yellow_fever_vaccinated' => array_key_exists('yellow_fever_vaccinated', $p)
                    ? (bool) $p['yellow_fever_vaccinated']
                    : null,
            ];
        }, $passengers);
    }
}
