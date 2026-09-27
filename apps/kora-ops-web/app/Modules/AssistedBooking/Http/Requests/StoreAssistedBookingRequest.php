<?php

namespace App\Modules\AssistedBooking\Http\Requests;

use App\Modules\AssistedBooking\Data\AssistedBookingChannel;
use App\Modules\Booking\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssistedBookingRequest extends FormRequest
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
            && $this->user()?->can('requestAssistance', $reservation) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(AssistedBookingChannel::class)],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'regex:/^\\+[1-9][0-9]{7,14}$/'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'channel' => ['description' => 'Canal de la demande d’assistance.'],
            'contact_name' => ['description' => 'Nom de la personne à contacter.'],
            'contact_phone' => ['description' => 'Numéro de téléphone international du contact.'],
            'idempotency_key' => ['description' => 'Clé d’idempotence de la demande.'],
        ];
    }
}
