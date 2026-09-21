<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\Role;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'phone')->whereNull('deleted_at'),
            ],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Suspended->value])],
            'agency_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('agencies', 'uuid')->whereNull('deleted_at'),
            ],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query
                    ->where('guard_name', 'web')
                    ->where('is_active', true)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $role = Role::query()
                ->where('name', (string) $this->input('role'))
                ->where('guard_name', 'web')
                ->where('is_active', true)
                ->first();

            if ($role === null) {
                return;
            }

            $requiresAgency = in_array($role->category->value, ['agency', 'terrain'], true);
            $hasAgency = filled($this->input('agency_uuid'));

            if ($requiresAgency && ! $hasAgency) {
                $validator->errors()->add('agency_uuid', 'Une agence est obligatoire pour ce rôle.');
            }

            if ($role->category->value === 'central' && $hasAgency) {
                $validator->errors()->add('agency_uuid', 'Un rôle central ne peut pas être rattaché à une agence.');
            }
        });
    }

    /**
     * @return array{name: string, email: string, phone: string|null, country_code: string|null, birth_date: string|null, status: string, agency_uuid: string|null, role: string}
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->trim()->value(),
            'email' => strtolower($this->string('email')->trim()->value()),
            'phone' => $this->string('phone')->trim()->value() ?: null,
            'country_code' => strtoupper($this->string('country_code')->trim()->value()) ?: null,
            'birth_date' => $this->input('birth_date') ?: null,
            'status' => $this->string('status')->trim()->value(),
            'agency_uuid' => $this->input('agency_uuid') ?: null,
            'role' => $this->string('role')->trim()->value(),
        ];
    }
}
