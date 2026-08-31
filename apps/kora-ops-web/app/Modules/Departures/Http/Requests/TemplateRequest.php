<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('departures.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'revision' => ['required', 'integer', 'min:0'],
            'ready' => ['required', 'boolean'],
            'slots' => ['present', 'array', 'max:32'],
            'slots.*' => ['array:key,direction,line_uuid,timezone,time,days,interval_weeks,duration_minutes,stops,bus_uuid,driver_uuid,secondary_driver_uuid,conductor_uuid,mechanic_uuid,return_key,return_day_offset,turnaround_minutes,preparation_minutes'],
            'slots.*.key' => ['required', 'uuid', 'distinct'],
            'slots.*.direction' => ['required', Rule::in(['outbound', 'return'])],
            'slots.*.line_uuid' => ['nullable', 'uuid', Rule::exists('lines', 'uuid')->whereNull('deleted_at')],
            'slots.*.timezone' => ['required', 'timezone'],
            'slots.*.time' => ['required', 'date_format:H:i'],
            'slots.*.days' => ['present', 'array', 'max:7'],
            'slots.*.days.*' => ['integer', 'between:1,7'],
            'slots.*.interval_weeks' => ['required', 'integer', 'between:1,12'],
            'slots.*.duration_minutes' => ['required', 'integer', 'between:1,10080'],
            'slots.*.preparation_minutes' => ['required', 'integer', 'between:0,1440'],
            'slots.*.return_key' => ['nullable', 'uuid'],
            'slots.*.return_day_offset' => ['required', 'integer', 'between:0,7'],
            'slots.*.turnaround_minutes' => ['required', 'integer', 'between:0,1440'],
            'slots.*.bus_uuid' => ['nullable', 'uuid', Rule::exists('buses', 'uuid')->whereNull('deleted_at')],
            'slots.*.driver_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'slots.*.secondary_driver_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'slots.*.conductor_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'slots.*.mechanic_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'slots.*.stops' => ['present', 'array', 'max:100'],
            'slots.*.stops.*' => ['array:stop_uuid,arrival_minutes,departure_minutes,timezone'],
            'slots.*.stops.*.stop_uuid' => ['required', 'uuid'],
            'slots.*.stops.*.timezone' => ['required', 'timezone'],
            'slots.*.stops.*.arrival_minutes' => ['nullable', 'integer', 'between:0,10080'],
            'slots.*.stops.*.departure_minutes' => ['nullable', 'integer', 'between:0,10080'],
        ];
    }
}
