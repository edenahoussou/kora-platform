<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Validation\Rule;

class UpdateAgencyRequest extends StoreAgencyRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('agency')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $agency = $this->route('agency');

        return [
            'code' => [
                'required',
                'string',
                'max:32',
                'alpha_dash',
                Rule::unique('agencies', 'code')->ignore($agency instanceof Agency ? $agency->id : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'head_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::enum(AgencyStatus::class)],
        ];
    }
}
