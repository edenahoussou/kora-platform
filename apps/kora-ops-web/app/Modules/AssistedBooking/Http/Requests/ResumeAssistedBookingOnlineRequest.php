<?php

namespace App\Modules\AssistedBooking\Http\Requests;

use App\Modules\Booking\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class ResumeAssistedBookingOnlineRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key') && $this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    public function authorize(): bool
    {
        $reservation = $this->route('reservation');

        return $reservation instanceof Reservation
            && $this->user()?->can('resumeOnline', $reservation) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.passenger_index' => ['required', 'integer', 'min:0'],
            'passengers.*.seat_assignments' => ['required', 'array', 'min:1'],
            'passengers.*.seat_assignments.*.leg_order' => ['required', 'integer', 'min:1'],
            'passengers.*.seat_assignments.*.seat_uuid' => ['required', 'uuid', 'distinct'],
        ];
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'idempotency_key' => ['description' => 'Clé d’idempotence de la demande.'],
            'passengers' => ['description' => 'Voyageurs de la réservation.'],
            'passengers.*.passenger_index' => ['description' => 'Index du voyageur dans la réservation.'],
            'passengers.*.seat_assignments' => ['description' => 'Attributions de sièges par segment.'],
            'passengers.*.seat_assignments.*.leg_order' => ['description' => 'Ordre du segment dans l’itinéraire.'],
            'passengers.*.seat_assignments.*.seat_uuid' => ['description' => 'UUID du siège attribué.'],
        ];
    }
}
