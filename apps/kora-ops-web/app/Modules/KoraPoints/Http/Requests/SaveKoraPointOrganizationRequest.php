<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Services\KoraPointTermsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKoraPointOrganizationRequest extends FormRequest
{
    /**
     * Grants access when the actor is active and holds the related permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.manage');
    }

    protected function prepareForValidation(): void
    {
        $members = $this->input('members');

        if (is_array($members)) {
            $this->merge([
                'members' => array_map(
                    fn (mixed $member): mixed => is_array($member)
                        ? [
                            ...$member,
                            'email' => is_string($member['email'] ?? null)
                                ? strtolower(trim($member['email']))
                                : ($member['email'] ?? null),
                        ]
                        : $member,
                    $members,
                ),
            ]);
        }
    }

    /**
     * Returns the payload validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $subject = $this->route('organization');
        $ignoredId = $subject instanceof KoraPointOrganization ? $subject->id : null;

        $rules = [
            'code' => ['sometimes', 'nullable', 'string', 'max:80', Rule::unique('kora_point_organizations', 'code')->ignore($ignoredId)],
            'name' => ['required', 'string', 'max:255'],
            'country' => ['required', Rule::in(['BJ', 'TG', 'CI'])],
            'registration_identifier' => ['nullable', 'string', 'max:120'],
            'tax_identifier' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'points' => ['sometimes', 'array', 'max:100'],
            'points.*' => ['array:code,name,city,department,country,responsible_name,email,phone,address,latitude,longitude,ceiling_amount'],
            'points.*.code' => ['required', 'string', 'max:80', 'distinct', 'unique:agencies,code'],
            'points.*.name' => ['required', 'string', 'max:255'],
            'points.*.city' => ['required', 'string', 'max:255'],
            'points.*.department' => ['nullable', 'string', 'max:120'],
            'points.*.country' => ['required', Rule::in(['BJ', 'TG', 'CI'])],
            'points.*.responsible_name' => ['required', 'string', 'max:255'],
            'points.*.email' => ['required', 'email:rfc', 'max:255', 'distinct', 'unique:users,email'],
            'points.*.phone' => ['nullable', 'string', 'max:40'],
            'points.*.address' => ['nullable', 'string', 'max:2000'],
            'points.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'points.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'points.*.ceiling_amount' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'members' => ['sometimes', 'array', 'max:50'],
            'members.*.name' => ['required', 'string', 'max:255'],
            'members.*.email' => ['required', 'email:rfc', 'max:255', 'distinct', 'unique:users,email'],
            'members.*.phone' => ['nullable', 'string', 'max:40'],
            'members.*.role' => ['required', 'string', Rule::in(['kora_point_supervisor', 'kora_point_treasurer'])],
        ];

        return array_replace($rules + KoraPointTermsService::rules(), [
            'commission_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'ceiling_amount' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'settlement_calendar' => ['required', 'array:frequency,time,timezone,weekday,day'],
            'settlement_calendar.frequency' => ['required', 'in:daily,weekly,monthly'],
            'settlement_calendar.time' => ['required', 'date_format:H:i'],
            'settlement_calendar.timezone' => ['required', 'timezone'],
            'settlement_calendar.weekday' => ['required_if:settlement_calendar.frequency,weekly', 'nullable', 'integer', 'between:1,7'],
            'settlement_calendar.day' => ['required_if:settlement_calendar.frequency,monthly', 'nullable', 'integer', 'between:1,28'],
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'members.*.email.unique' => 'Cette adresse e-mail est déjà associée à un compte. Affectez ce compte existant depuis Utilisateurs au lieu de créer un doublon.',
        ];
    }
}
