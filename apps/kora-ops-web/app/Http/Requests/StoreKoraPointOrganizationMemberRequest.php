<?php

namespace App\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKoraPointOrganizationMemberRequest extends FormRequest
{
    /**
     * Grants access to internal users who can manage users and KORA POINT.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->status === UserStatus::Active
            && ! $user->hasAnyRole(['kora_point_agent', 'kora_point_supervisor', 'kora_point_treasurer'])
            && $user->can('users.manage')
            && $user->can('kora-points.manage');
    }

    /**
     * Returns the validated organization member fields.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->whereNull('deleted_at')],
            'role' => ['required', 'string', Rule::in(['kora_point_supervisor', 'kora_point_treasurer'])],
        ];
    }

    /** @return array{name: string, email: string, phone?: string|null, role: string} */
    public function memberAttributes(): array
    {
        $validated = $this->validated();
        $name = $validated['name'] ?? null;
        $email = $validated['email'] ?? null;
        $phone = $validated['phone'] ?? null;
        $role = $validated['role'] ?? null;

        if (! is_string($name) || ! is_string($email) || ! is_string($role) || (! is_string($phone) && $phone !== null)) {
            throw new \LogicException('Validated organization member attributes have unexpected types.');
        }

        return ['name' => $name, 'email' => $email, 'phone' => $phone, 'role' => $role];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'),
            'phone' => is_string($this->input('phone')) ? trim($this->input('phone')) : $this->input('phone'),
            'role' => is_string($this->input('role')) ? trim($this->input('role')) : $this->input('role'),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Saisissez le nom du membre.',
            'email.required' => 'L’adresse e-mail est obligatoire pour inviter le membre.',
            'email.email' => 'Saisissez une adresse e-mail valide.',
            'email.unique' => 'Cette adresse e-mail est déjà associée à un compte.',
            'phone.unique' => 'Ce numéro de téléphone est déjà associé à un compte.',
            'role.required' => 'Choisissez le rôle du membre.',
            'role.in' => 'Choisissez un rôle autorisé pour l’organisation.',
        ];
    }
}
