<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('departures.manage') ?? false)
            && $this->user()->can('departures.cancel');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'password' => ['required', 'string', 'current_password'],
            'filters' => ['sometimes', 'array'],
            'filters.line' => ['sometimes', 'nullable', 'string'],
            'filters.bus' => ['sometimes', 'nullable', 'string'],
            'filters.programme' => ['sometimes', 'nullable', 'string'],
            'filters.direction' => ['sometimes', 'nullable', 'string', 'in:outbound,return'],
            'filters.person' => ['sometimes', 'nullable', 'string'],
            'filters.state' => ['sometimes', 'nullable', 'string'],
            'filters.exceptions' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
