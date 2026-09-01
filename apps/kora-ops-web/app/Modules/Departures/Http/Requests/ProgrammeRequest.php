<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgrammeRequest extends FormRequest
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
            'version_uuid' => ['required', 'uuid', 'exists:departure_template_versions,uuid'],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'horizon_days' => ['required', 'integer', 'between:7,90'],
            'exceptions' => ['present', 'array', 'max:500'],
            'exceptions.*' => ['array:date,slot_key,action,time,bus_uuid,driver_uuid,secondary_driver_uuid,conductor_uuid,mechanic_uuid'],
            'exceptions.*.date' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:ends_on'],
            'exceptions.*.slot_key' => ['required', 'uuid'],
            'exceptions.*.action' => ['required', Rule::in(['exclude', 'change', 'add'])],
            'exceptions.*.time' => ['nullable', 'date_format:H:i'],
            'exceptions.*.bus_uuid' => ['nullable', 'uuid', Rule::exists('buses', 'uuid')->whereNull('deleted_at')],
            'exceptions.*.driver_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'exceptions.*.secondary_driver_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'exceptions.*.conductor_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'exceptions.*.mechanic_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
        ];
    }
}
