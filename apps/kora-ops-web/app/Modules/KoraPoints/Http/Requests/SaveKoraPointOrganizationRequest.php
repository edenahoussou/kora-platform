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
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'points' => ['sometimes', 'array', 'max:100'],
            'points.*' => ['array:code,name,city,country,responsible_name,phone,address,latitude,longitude'],
            'points.*.code' => ['required', 'string', 'max:80', 'distinct', 'unique:agencies,code'],
            'points.*.name' => ['required', 'string', 'max:255'],
            'points.*.city' => ['required', 'string', 'max:255'],
            'points.*.country' => ['required', 'regex:/^[A-Z]{2}$/'],
            'points.*.responsible_name' => ['required', 'string', 'max:255'],
            'points.*.phone' => ['required', 'string', 'max:40'],
            'points.*.address' => ['nullable', 'string', 'max:2000'],
            'points.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'points.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'members' => ['sometimes', 'array', 'max:50'],
            'members.*.name' => ['required', 'string', 'max:255'],
            'members.*.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'members.*.phone' => ['nullable', 'string', 'max:40'],
            'members.*.role' => ['required', 'string', Rule::in(['kora_point_supervisor', 'kora_point_treasurer'])],
        ];

        return $rules + KoraPointTermsService::rules();
    }
}
