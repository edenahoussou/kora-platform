<?php

namespace App\Modules\Parcels\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParcelConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'hub' => [
                'agency_uuid' => ['nullable', 'uuid', 'required_without:kora_point_uuid', 'prohibits:kora_point_uuid'],
                'kora_point_uuid' => ['nullable', 'uuid', 'required_without:agency_uuid', 'prohibits:agency_uuid'],
                'stop_uuid' => ['required', 'uuid'],
                'enabled' => ['required', 'boolean'],
                'supports_weighing' => ['sometimes', 'boolean'],
            ],
            'routing' => [
                'origin_hub_uuid' => ['required', 'uuid'],
                'destination_hub_uuid' => ['required', 'uuid', 'different:origin_hub_uuid'],
            ],
            'corridor' => [
                'origin_hub_uuid' => ['required', 'uuid'],
                'destination_hub_uuid' => ['required', 'uuid', 'different:origin_hub_uuid'],
                'enabled' => ['required', 'boolean'],
                'wallet_cash_enabled' => ['required', 'boolean'],
            ],
            'tariff' => [
                'deposit_kind' => ['sometimes', 'string', 'in:agency,partner_point'],
                'base_amount' => ['required', 'integer', 'min:1', 'max:10000000'],
                'step_amount' => ['required', 'integer', 'min:0', 'max:1000000'],
                'step_grams' => ['required', 'integer', 'min:100', 'max:30000'],
                'maximum_grams' => ['required', 'integer', 'gte:step_grams', 'max:30000'],
                'return_amount' => ['required', 'integer', 'min:1', 'max:10000000'],
            ],
            'quote' => [
                'deposit_hub_uuid' => ['required', 'uuid'],
                'destination_hub_uuid' => ['required', 'uuid', 'different:deposit_hub_uuid'],
                'weight_grams' => ['required', 'integer', 'min:1', 'max:1000000'],
            ],
            'capacity' => [
                'capacity_grams' => ['required', 'integer', 'min:0', 'max:10000000'],
            ],
            default => [],
        };
    }
}
