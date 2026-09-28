<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Actions\ResolveStopsPayloadAction;
use App\Modules\BusNetwork\Data\LineStatus;
use App\Modules\BusNetwork\Data\LineType;
use App\Modules\BusNetwork\Models\Line;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $line = $this->route('line');

        return $this->user()?->can('update', $line) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Line|null $line */
        $line = $this->route('line');
        $lineId = $line?->id;

        return [
            'code' => ['sometimes', 'required', 'string', 'max:32', Rule::unique('lines', 'code')->ignore($lineId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(LineType::class)],
            'bidirectional' => ['sometimes', 'boolean'],
            'countries_crossed' => ['nullable', 'array'],
            'countries_crossed.*' => ['string', 'max:2'],
            'distance_km' => ['nullable', 'integer', 'min:0'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'required', Rule::enum(LineStatus::class)],
            'stops' => ['nullable', 'array'],
            'stops.*.uuid' => ['nullable', 'string'],
            'stops.*.name' => ['required_with:stops', 'string', 'max:255'],
            'stops.*.city' => ['required_with:stops', 'string', 'max:255'],
            'stops.*.country' => ['required_with:stops', 'string', 'max:2'],
            'stops.*.stop_order' => ['required_with:stops', 'integer', 'min:1'],
            'stops.*.physical_stop_uuid' => ['required_without:stops.*.physical_stop', 'string', 'exists:physical_stops,uuid'],
            'stops.*.physical_stop' => ['required_without:stops.*.physical_stop_uuid', 'array'],
            'stops.*.physical_stop.name' => ['required_with:stops.*.physical_stop', 'string', 'max:255'],
            'stops.*.physical_stop.city' => ['required_with:stops.*.physical_stop', 'string', 'max:255'],
            'stops.*.physical_stop.country' => ['required_with:stops.*.physical_stop', 'string', 'max:2'],
            'stops.*.physical_stop.latitude' => ['required_with:stops.*.physical_stop', 'numeric', 'between:-90,90'],
            'stops.*.physical_stop.longitude' => ['required_with:stops.*.physical_stop', 'numeric', 'between:-180,180'],
            'stops.*.km_from_origin' => ['nullable', 'numeric', 'min:0'],
            'stops.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'stops.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array{code?: string, name?: string, type?: string, bidirectional?: bool, countries_crossed?: array<string>|null, distance_km?: int|null, estimated_duration_minutes?: int|null, status?: string}
     */
    public function payload(): array
    {
        $payload = [];

        if ($this->has('code')) {
            $payload['code'] = strtoupper($this->string('code')->trim()->value());
        }
        if ($this->has('name')) {
            $payload['name'] = $this->string('name')->trim()->value();
        }
        if ($this->has('type')) {
            $payload['type'] = $this->string('type')->trim()->value();
        }
        if ($this->has('bidirectional')) {
            $payload['bidirectional'] = $this->boolean('bidirectional');
        }
        if ($this->has('countries_crossed')) {
            $payload['countries_crossed'] = $this->array('countries_crossed') ?: null;
        }
        if ($this->has('distance_km')) {
            $payload['distance_km'] = $this->filled('distance_km') ? (int) $this->input('distance_km') : null;
        }
        if ($this->has('estimated_duration_minutes')) {
            $payload['estimated_duration_minutes'] = $this->filled('estimated_duration_minutes') ? (int) $this->input('estimated_duration_minutes') : null;
        }
        if ($this->has('status')) {
            $payload['status'] = $this->string('status')->trim()->value();
        }

        return $payload;
    }

    /**
     * @return array<int, array{uuid?: string|null, name: string, city: string, country: string, stop_order: int, physical_stop_id: int, km_from_origin: float, latitude: float|null, longitude: float|null}>
     */
    public function stops(): array
    {
        return app(ResolveStopsPayloadAction::class)->execute($this->array('stops'));
    }
}
