<?php

namespace App\Modules\Booking\Http\Requests;

use App\Http\Requests\JsonValidationRequest;
use App\Modules\Booking\Models\Reservation;

class UpdateCounterSalePromoCodeRequest extends JsonValidationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createCounterSale', Reservation::class) ?? false;
    }

    /**
     * `promo_code` doit être présent : une valeur non nulle applique la
     * promotion, `null` retire la remise en cours sur la réservation.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'promo_code' => ['present', 'nullable', 'string', 'max:32'],
            'itinerary_quote_uuid' => ['nullable', 'uuid'],
            'trip_type' => ['nullable', 'in:one_way,round_trip'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function promoCode(): ?string
    {
        $value = $this->input('promo_code');

        return $value === null ? null : trim((string) $value);
    }

    /**
     * @return array<string, string|null>
     */
    public function saleQuery(string $reservationUuid): array
    {
        return array_filter([
            'date' => $this->string('date')->value() ?: null,
            'from' => $this->string('from')->trim()->value() ?: null,
            'to' => $this->string('to')->trim()->value() ?: null,
            'itinerary_quote_uuid' => $this->string('itinerary_quote_uuid')->value() ?: null,
            'reservation_uuid' => $reservationUuid,
            'trip_type' => $this->string('trip_type')->value() ?: null,
            'return_date' => $this->string('return_date')->value() ?: null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
