<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanningMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('departures.manage') ?? false)
            && ($this->input('operation') !== 'exclude' || $this->user()->can('departures.cancel'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['preview', 'generate', 'publish', 'assign', 'exclude', 'change', 'automatic', 'revision', 'archive'])],
            'revision' => ['sometimes', 'integer', 'min:0'],
            'automatic' => ['required_if:operation,automatic', 'boolean'],
            'horizon_days' => ['sometimes', 'integer', 'between:7,90'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'until' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'version_uuid' => ['required_if:operation,revision', 'uuid', 'exists:departure_template_versions,uuid'],
            'apply' => ['sometimes', 'boolean'],
            'token' => ['nullable', 'string', 'max:128'],
            'items' => ['required_if:operation,publish,assign,exclude,change', 'array', 'max:500'],
            'items.*' => ['array:uuid,revision'],
            'items.*.uuid' => ['required', 'uuid', 'distinct', 'exists:planned_departures,uuid'],
            'items.*.revision' => ['required', 'integer', 'min:1'],
            'changes' => ['sometimes', 'array:time,bus_uuid,driver_uuid,secondary_driver_uuid,conductor_uuid,mechanic_uuid'],
            'changes.time' => ['sometimes', 'required', 'date_format:H:i'],
            'changes.bus_uuid' => ['sometimes', 'nullable', 'uuid', Rule::exists('buses', 'uuid')->whereNull('deleted_at')],
            'changes.driver_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:users,uuid'],
            'changes.secondary_driver_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:users,uuid'],
            'changes.conductor_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:users,uuid'],
            'changes.mechanic_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:users,uuid'],
        ];
    }
}
