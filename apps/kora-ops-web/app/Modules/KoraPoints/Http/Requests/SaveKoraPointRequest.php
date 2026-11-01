<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKoraPointRequest extends FormRequest
{
    /**
     * Grants access when the actor is active and holds the related permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.manage');
    }

    /**
     * Normalizes wizard aliases (manager name, credit limit, frequency) into canonical fields.
     */
    protected function prepareForValidation(): void
    {
        $calendar = $this->input('settlement_calendar');
        if (is_string($calendar)) {
            $freq = in_array($calendar, ['weekly', 'monthly', 'daily'], true) ? $calendar : 'weekly';
            $calendar = [
                'frequency' => $freq,
                'weekday' => $freq === 'weekly' ? 1 : null,
                'day' => $freq === 'monthly' ? 1 : null,
                'time' => '18:00',
                'timezone' => 'Africa/Porto-Novo',
            ];
        } elseif ($calendar === null && ($this->has('settlementFrequencyDays') || $this->has('settlement_frequency_days'))) {
            $days = (int) ($this->input('settlementFrequencyDays') ?? $this->input('settlement_frequency_days'));
            $freq = $days >= 28 ? 'monthly' : ($days >= 7 ? 'weekly' : 'daily');
            $calendar = [
                'frequency' => $freq,
                'weekday' => $freq === 'weekly' ? 1 : null,
                'day' => $freq === 'monthly' ? 1 : null,
                'time' => '18:00',
                'timezone' => 'Africa/Porto-Novo',
            ];
        }

        $this->merge([
            'country' => $this->input('country') ?: 'BJ',
            'responsible_name' => $this->input('responsible_name') ?? $this->input('managerName') ?? $this->input('manager_name'),
            'organization_uuid' => $this->input('organization_uuid') ?? $this->input('organizationUuid'),
            'commission_basis_points' => $this->input('commission_basis_points') ?? $this->input('commissionRateBps'),
            'ceiling_amount' => $this->input('ceiling_amount') ?? $this->input('creditLimit'),
            'settlement_calendar' => $calendar,
        ]);
    }

    /**
     * Returns the payload validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $subject = $this->route('point');
        $ignoredId = $subject instanceof KoraPoint ? $subject->agency_id : null;

        return [
            'code' => ['nullable', 'string', 'max:80', Rule::unique('agencies', 'code')->ignore($ignoredId)],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'responsible_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'organization_uuid' => ['nullable', 'uuid', 'exists:kora_point_organizations,uuid'],
            'commission_basis_points' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'ceiling_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'settlement_calendar' => ['nullable', 'array:frequency,time,timezone,weekday,day'],
            'settlement_calendar.frequency' => ['required_with:settlement_calendar', 'in:daily,weekly,monthly'],
            'settlement_calendar.time' => ['required_with:settlement_calendar', 'date_format:H:i'],
            'settlement_calendar.timezone' => ['required_with:settlement_calendar', 'timezone'],
            'settlement_calendar.weekday' => ['required_if:settlement_calendar.frequency,weekly', 'nullable', 'integer', 'between:1,7'],
            'settlement_calendar.day' => ['required_if:settlement_calendar.frequency,monthly', 'nullable', 'integer', 'between:1,28'],
        ];
    }
}
