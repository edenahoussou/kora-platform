<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\BusAmenityStatus;
use App\Modules\BusNetwork\Models\BusAmenity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusAmenityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('amenity')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var BusAmenity|null $amenity */
        $amenity = $this->route('amenity');

        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('bus_amenities', 'code')->ignore($amenity?->id)],
            'label' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::enum(BusAmenityStatus::class)],
        ];
    }

    /** @return array{code: string, label: string, icon: string|null, status: string} */
    public function payload(): array
    {
        return [
            'code' => mb_strtolower($this->string('code')->trim()->value()),
            'label' => $this->string('label')->trim()->value(),
            'icon' => $this->filled('icon') ? $this->string('icon')->trim()->value() : null,
            'status' => $this->string('status')->trim()->value(),
        ];
    }
}
