<?php

namespace App\Modules\AssistedBooking\Http\Requests;

use App\Modules\AssistedBooking\Models\AssistedBooking;
use Illuminate\Foundation\Http\FormRequest;

class PrepareAssistedBookingSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assistance = $this->route('assistedBooking');

        return $assistance instanceof AssistedBooking && $this->user()?->can('prepareSale', $assistance) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.passenger_index' => ['required', 'integer', 'min:0'],
            'passengers.*.seat_assignments' => ['required', 'array', 'min:1'],
            'passengers.*.seat_assignments.*.leg_order' => ['required', 'integer', 'min:1'],
            'passengers.*.seat_assignments.*.seat_uuid' => ['required', 'uuid', 'distinct'],
        ];
    }
}
