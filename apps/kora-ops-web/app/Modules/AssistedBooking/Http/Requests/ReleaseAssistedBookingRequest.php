<?php

namespace App\Modules\AssistedBooking\Http\Requests;

use App\Modules\AssistedBooking\Models\AssistedBooking;
use Illuminate\Foundation\Http\FormRequest;

class ReleaseAssistedBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assistance = $this->route('assistedBooking');

        return $assistance instanceof AssistedBooking && $this->user()?->can('release', $assistance) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:1000']];
    }
}
