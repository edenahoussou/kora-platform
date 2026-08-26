<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\BusAmenityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeBusAmenityStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deactivate', $this->route('amenity')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(BusAmenityStatus::class)]];
    }

    public function status(): BusAmenityStatus
    {
        return BusAmenityStatus::from($this->string('status')->trim()->value());
    }
}
