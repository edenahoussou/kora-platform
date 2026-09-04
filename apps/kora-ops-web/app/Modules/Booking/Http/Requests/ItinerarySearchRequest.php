<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Models\GroupBookingSetting;
use Illuminate\Foundation\Http\FormRequest;

class ItinerarySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'from_physical_stop_uuid' => ['required', 'uuid', 'exists:physical_stops,uuid'],
            'to_physical_stop_uuid' => ['required', 'uuid', 'exists:physical_stops,uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'passenger_count' => ['sometimes', 'integer', 'min:1', 'max:'.GroupBookingSetting::current()->maximum_passengers],
        ];
    }
}
