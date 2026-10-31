<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKoraPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $subject = $this->route('point');
        $ignoredId = $subject instanceof KoraPoint ? $subject->agency_id : null;

        return [
            'code' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:80', Rule::unique('agencies', 'code')->ignore($ignoredId)],
            'name' => ['required', 'string', 'max:255'], 'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'responsible_name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'organization_uuid' => ['nullable', 'uuid', 'exists:kora_point_organizations,uuid'],
        ];
    }
}
