<?php

namespace App\Modules\Booking\Http\Requests;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class ReleaseSeatHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $reservation = $this->route('reservation');

        return $user instanceof User
            && $reservation instanceof Reservation
            && $user->can('cancel', $reservation);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
