<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class AssignKoraPointUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('users.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['user_uuid' => ['required', 'uuid', 'exists:users,uuid'], 'role' => ['required', 'in:kora_point_agent,kora_point_supervisor,kora_point_treasurer']];
    }
}
