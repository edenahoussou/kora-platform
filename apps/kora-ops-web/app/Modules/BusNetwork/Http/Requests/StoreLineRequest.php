<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\LineStatus;
use App\Modules\BusNetwork\Data\LineType;
use App\Modules\BusNetwork\Models\Line;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Line::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('lines', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(LineType::class)],
            'bidirectional' => ['sometimes', 'boolean'],
            'countries_crossed' => ['nullable', 'array'],
            'countries_crossed.*' => ['string', 'max:2'],
            'distance_km' => ['nullable', 'integer', 'min:0'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(LineStatus::class)],
            'stops' => ['nullable', 'array'],
            'stops.*.name' => ['required_with:stops', 'string', 'max:255'],
            'stops.*.city' => ['required_with:stops', 'string', 'max:255'],
            'stops.*.country' => ['required_with:stops', 'string', 'max:2'],
            'stops.*.stop_order' => ['required_with:stops', 'integer', 'min:1'],
            'stops.*.km_from_origin' => ['nullable', 'numeric', 'min:0'],
            'stops.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'stops.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array{code: string, name: string, type: string, bidirectional: bool, countries_crossed: array<string>|null, distance_km: int|null, estimated_duration_minutes: int|null, status: string}
     */
    public function payload(): array
    {
        return [
            'code' => strtoupper($this->string('code')->trim()->value()),
            'name' => $this->string('name')->trim()->value(),
            'type' => $this->string('type')->trim()->value(),
            'bidirectional' => $this->boolean('bidirectional', true),
            'countries_crossed' => $this->array('countries_crossed') ?: null,
            'distance_km' => $this->filled('distance_km') ? (int) $this->input('distance_km') : null,
            'estimated_duration_minutes' => $this->filled('estimated_duration_minutes') ? (int) $this->input('estimated_duration_minutes') : null,
            'status' => $this->string('status')->trim()->value(),
        ];
    }

    /**
     * @return array<int, array{name: string, city: string, country: string, stop_order: int, km_from_origin: float, latitude: float|null, longitude: float|null}>
     */
    public function stops(): array
    {
        $stops = $this->array('stops');

        return collect($stops)->map(fn (array $stop): array => [
            'name' => trim((string) $stop['name']),
            'city' => trim((string) $stop['city']),
            'country' => strtoupper(trim((string) $stop['country'])),
            'stop_order' => (int) $stop['stop_order'],
            'km_from_origin' => isset($stop['km_from_origin']) ? (float) $stop['km_from_origin'] : 0.0,
            'latitude' => isset($stop['latitude']) ? (float) $stop['latitude'] : null,
            'longitude' => isset($stop['longitude']) ? (float) $stop['longitude'] : null,
        ])->values()->all();
    }
}
