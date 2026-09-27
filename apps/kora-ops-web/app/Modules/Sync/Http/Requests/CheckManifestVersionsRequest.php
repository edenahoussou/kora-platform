<?php

namespace App\Modules\Sync\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckManifestVersionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'departures' => ['required', 'array', 'min:1', 'max:100'],
            'departures.*.departure_uuid' => ['required', 'string', 'distinct'],
            'departures.*.known_version' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, array{departure_uuid: string, known_version: string|null}>
     */
    public function departures(): array
    {
        return array_map(fn (array $d) => [
            'departure_uuid' => $d['departure_uuid'],
            'known_version' => $d['known_version'] ?? null,
        ], $this->input('departures', []));
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'departures' => ['description' => 'Valeur de « departures ».'],
            'departures.*.departure_uuid' => ['description' => 'UUID du départ concerné.'],
            'departures.*.known_version' => ['description' => 'Valeur de « known version ».'],
        ];
    }
}
