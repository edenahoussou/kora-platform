<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItinerarySearchRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'from_physical_stop_uuid' => ['required', 'uuid', 'exists:physical_stops,uuid'],
            'to_physical_stop_uuid' => ['required', 'uuid', 'exists:physical_stops,uuid'],
            'date' => ['required', 'date'],
        ];
    }
}
