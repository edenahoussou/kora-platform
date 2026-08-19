<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\StopStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        $stop = $this->route('stop');

        return $this->user()?->can('update', $stop) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:255'],
            'country' => ['sometimes', 'required', 'string', 'max:2'],
            'stop_order' => ['sometimes', 'required', 'integer', 'min:1'],
            'km_from_origin' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', 'required', Rule::enum(StopStatus::class)],
        ];
    }

    /**
     * @return array{name?: string, city?: string, country?: string, stop_order?: int, km_from_origin?: float, latitude?: float|null, longitude?: float|null, status?: string}
     */
    public function payload(): array
    {
        $payload = [];

        if ($this->has('name')) {
            $payload['name'] = $this->string('name')->trim()->value();
        }
        if ($this->has('city')) {
            $payload['city'] = $this->string('city')->trim()->value();
        }
        if ($this->has('country')) {
            $payload['country'] = strtoupper($this->string('country')->trim()->value());
        }
        if ($this->has('stop_order')) {
            $payload['stop_order'] = (int) $this->input('stop_order');
        }
        if ($this->has('km_from_origin')) {
            $payload['km_from_origin'] = (float) $this->input('km_from_origin');
        }
        if ($this->has('latitude')) {
            $payload['latitude'] = $this->filled('latitude') ? (float) $this->input('latitude') : null;
        }
        if ($this->has('longitude')) {
            $payload['longitude'] = $this->filled('longitude') ? (float) $this->input('longitude') : null;
        }
        if ($this->has('status')) {
            $payload['status'] = $this->string('status')->trim()->value();
        }

        return $payload;
    }
}
