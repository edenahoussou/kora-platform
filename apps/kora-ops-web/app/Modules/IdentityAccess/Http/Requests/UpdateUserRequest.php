<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->getKey() : null;

        return [
            ...parent::rules(),
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'phone')->ignore($userId)->whereNull('deleted_at'),
            ],
        ];
    }
}
