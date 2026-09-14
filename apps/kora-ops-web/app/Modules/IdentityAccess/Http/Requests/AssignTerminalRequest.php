<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign', $this->route('terminal')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'agency_id' => ['nullable', 'integer', Rule::exists((new Agency)->getTable(), 'id')],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('assigned_user_id') && ! $this->filled('agency_id')) {
                $validator->errors()->add('assigned_user_id', 'Choisissez un utilisateur terrain ou une agence.');
            }
        });
    }

    /**
     * @return array{assigned_user_id: int|null, agency_id: int|null, reason: string|null}
     */
    public function payload(): array
    {
        return [
            'assigned_user_id' => $this->integer('assigned_user_id') ?: null,
            'agency_id' => $this->integer('agency_id') ?: null,
            'reason' => $this->string('reason')->trim()->value() ?: null,
        ];
    }
}
