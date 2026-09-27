<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupBookingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('group-discounts.manage') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'minimum_passengers' => ['required', 'integer', 'min:1'],
            'maximum_passengers' => ['required', 'integer', 'gte:minimum_passengers'],
        ];
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'minimum_passengers' => ['description' => 'Nombre minimal de passagers.'],
            'maximum_passengers' => ['description' => 'Nombre maximal de passagers.'],
        ];
    }
}
