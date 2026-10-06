<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Models\GroupBookingSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItineraryQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'passenger_count' => ['sometimes', 'integer', 'min:1', 'max:'.GroupBookingSetting::current()->maximum_passengers],
            'promo_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'legs' => ['required', 'array', 'min:1', 'max:6'],
            'legs.*.departure_uuid' => ['required', 'uuid', 'exists:departures,uuid'],
            'legs.*.origin_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'legs.*.destination_stop_uuid' => ['required', 'uuid', 'exists:stops,uuid'],
            'legs.*.leg_order' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'legs.*.direction' => ['sometimes', 'string', 'in:outbound,inbound'],
            'payer' => ['sometimes', 'array'],
            'payer.type' => ['required_with:payer', Rule::in(['personal', 'company'])],
            'payer.company_account_uuid' => ['required_if:payer.type,company', 'uuid', 'exists:company_accounts,uuid'],
        ];
    }

    /** @return array<string, mixed> */
    public function payer(): array
    {
        return $this->input('payer', ['type' => 'personal']);
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'passenger_count' => ['description' => 'Nombre de passagers.'],
            'promo_code' => ['description' => 'Code promotionnel à appliquer.'],
            'legs' => ['description' => 'Segments de trajet de l’itinéraire.'],
            'legs.*.departure_uuid' => ['description' => 'UUID du départ concerné.'],
            'legs.*.origin_stop_uuid' => ['description' => 'Valeur de « origin stop uuid ».'],
            'legs.*.destination_stop_uuid' => ['description' => 'Valeur de « destination stop uuid ».'],
            'legs.*.leg_order' => ['description' => 'Ordre du segment dans l’itinéraire.'],
            'legs.*.direction' => ['description' => 'Ordre de tri ou direction du trajet.'],
            'payer' => ['description' => 'Informations sur le payeur.'],
            'payer.type' => ['description' => 'Type de l’événement ou de la ressource.'],
            'payer.company_account_uuid' => ['description' => 'UUID du compte entreprise payeur.'],
        ];
    }
}
