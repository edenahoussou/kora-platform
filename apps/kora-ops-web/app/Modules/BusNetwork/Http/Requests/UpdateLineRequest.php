<?php

namespace App\Modules\BusNetwork\Http\Requests;

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
}
