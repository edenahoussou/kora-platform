<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationPromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * `promo_code` doit être présent : une valeur non nulle applique la
     * promotion, `null` retire la remise en cours sur la réservation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'promo_code' => ['present', 'nullable', 'string', 'max:32'],
        ];
    }

    public function promoCode(): ?string
    {
        $value = $this->input('promo_code');

        return $value === null ? null : trim((string) $value);
    }
}
