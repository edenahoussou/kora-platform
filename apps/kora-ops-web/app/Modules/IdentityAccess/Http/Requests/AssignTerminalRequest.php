<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Models\User;
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
            'assigned_user_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'agency_id' => ['nullable', 'integer', Rule::exists((new Agency)->getTable(), 'id')],
            'agency_uuid' => ['nullable', 'uuid', Rule::exists((new Agency)->getTable(), 'uuid')],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('assigned_user_id') && ! $this->filled('assigned_user_uuid') && ! $this->filled('agency_id') && ! $this->filled('agency_uuid')) {
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
            'assigned_user_id' => $this->integer('assigned_user_id')
                ?: ($this->filled('assigned_user_uuid')
                    ? User::query()->where('uuid', $this->string('assigned_user_uuid')->trim()->value())->value('id')
                    : null),
            'agency_id' => $this->integer('agency_id')
                ?: ($this->filled('agency_uuid')
                    ? Agency::query()->where('uuid', $this->string('agency_uuid')->trim()->value())->value('id')
                    : null),
            'reason' => $this->string('reason')->trim()->value() ?: null,
        ];
    }
}
