<?php

namespace App\Modules\AssistedBooking\Http\Requests;

use App\Modules\AssistedBooking\Models\AssistedBooking;
use Illuminate\Foundation\Http\FormRequest;

class RefuseAssistedBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assistance = $this->route('assistedBooking');

        return $assistance instanceof AssistedBooking && $this->user()?->can('refuse', $assistance) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', 'max:64'],
            'reason_note' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
