<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\BusStatus;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusAmenity;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\BusModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('bus')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Bus|null $bus */
        $bus = $this->route('bus');

        return [
            'registration_number' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9\s\-_]+$/', Rule::unique('buses', 'registration_number')->ignore($bus?->id)],
            'bus_model_id' => ['required', 'integer', Rule::exists(BusModel::class, 'id')->whereNull('deleted_at')],
            'composition_id' => ['required', 'integer', Rule::exists(BusComposition::class, 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(BusStatus::class)],
            'amenity_ids' => ['sometimes', 'array'],
            'amenity_ids.*' => ['integer', Rule::exists(BusAmenity::class, 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array{registration_number: string, bus_model_id: int, composition_id: int, status: string, amenity_ids: list<int>} */
    public function payload(): array
    {
        return [
            'registration_number' => strtoupper($this->string('registration_number')->trim()->value()),
            'bus_model_id' => $this->integer('bus_model_id'),
            'composition_id' => $this->integer('composition_id'),
            'status' => $this->string('status')->trim()->value(),
            'amenity_ids' => array_values(array_map('intval', $this->array('amenity_ids'))),
        ];
    }
}
