<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Foundation\Http\FormRequest;

class StoreStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Stop::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:2'],
            'stop_order' => ['required', 'integer', 'min:1'],
            'physical_stop_uuid' => ['required', 'string', 'exists:physical_stops,uuid'],
            'km_from_origin' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array{name: string, city: string, country: string, stop_order: int, physical_stop_id: int, km_from_origin: float, latitude: float|null, longitude: float|null}
     */
    public function payload(): array
    {
        $physicalStop = PhysicalStop::query()->where('uuid', $this->string('physical_stop_uuid')->toString())->firstOrFail();

        return [
            'name' => $this->string('name')->trim()->value(),
            'city' => $this->string('city')->trim()->value(),
            'country' => strtoupper($this->string('country')->trim()->value()),
            'stop_order' => (int) $this->input('stop_order'),
            'physical_stop_id' => $physicalStop->id,
            'km_from_origin' => $this->has('km_from_origin') ? (float) $this->input('km_from_origin') : 0.0,
            'latitude' => $this->has('latitude') ? (float) $this->input('latitude') : null,
            'longitude' => $this->has('longitude') ? (float) $this->input('longitude') : null,
        ];
    }
}
