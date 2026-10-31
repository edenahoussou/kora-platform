<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKoraPointOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $subject = $this->route('organization');
        $ignoredId = $subject instanceof KoraPointOrganization ? $subject->id : null;

        return [
            'code' => ['required', 'string', 'max:80', Rule::unique('kora_point_organizations', 'code')->ignore($ignoredId)], 'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:2000'],
            'points' => ['sometimes', 'array', 'max:100'], 'points.*' => ['array:code,name,city,country,responsible_name,phone,address,latitude,longitude'],
            'points.*.code' => ['required', 'string', 'max:80', 'distinct', 'unique:agencies,code'], 'points.*.name' => ['required', 'string', 'max:255'],
            'points.*.city' => ['required', 'string', 'max:255'], 'points.*.country' => ['required', 'regex:/^[A-Z]{2}$/'],
            'points.*.responsible_name' => ['required', 'string', 'max:255'], 'points.*.phone' => ['required', 'string', 'max:40'],
            'points.*.address' => ['nullable', 'string', 'max:2000'], 'points.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'points.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
