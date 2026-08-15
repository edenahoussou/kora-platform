<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'max:4'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }

    /**
     * @return array{name: string, email: string, phone?: string|null, country_code?: string|null, birth_date?: string|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'phone' => isset($validated['phone']) ? (string) $validated['phone'] : null,
            'country_code' => isset($validated['country_code']) ? (string) $validated['country_code'] : null,
            'birth_date' => isset($validated['birth_date']) ? (string) $validated['birth_date'] : null,
        ];
    }
}
