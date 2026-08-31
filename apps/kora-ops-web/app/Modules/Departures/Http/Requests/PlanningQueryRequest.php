<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanningQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('departures.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'view' => ['sometimes', Rule::in(['week', 'month', 'list'])],
            'tab' => ['sometimes', Rule::in(['planning', 'templates', 'programmes'])],
            'line' => ['nullable', 'uuid'],
            'bus' => ['nullable', 'uuid'], 'person' => ['nullable', 'uuid'],
            'programme' => ['nullable', 'uuid'],
            'template' => ['nullable', 'uuid'],
            'source' => ['nullable', 'uuid'],
            'departure' => ['nullable', 'uuid'],
            'direction' => ['nullable', Rule::in(['outbound', 'return'])],
            'state' => ['nullable', Rule::in(['unassigned', 'blocked', 'ready', 'published', 'excluded'])],
            'exceptions' => ['sometimes', 'boolean'],
            'q' => ['nullable', 'string', 'max:160'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
