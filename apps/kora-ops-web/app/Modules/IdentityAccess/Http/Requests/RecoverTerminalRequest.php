<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecoverTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changeStatus', $this->route('terminal')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_user_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'agency_id' => ['nullable', 'integer', Rule::exists((new Agency)->getTable(), 'id')],
            'agency_uuid' => ['nullable', 'uuid', Rule::exists((new Agency)->getTable(), 'uuid')],
        ];
    }

    /**
     * @return array{reason: string, assigned_user_id: int|null, agency_id: int|null}
     */
    public function payload(): array
    {
        return [
            'reason' => $this->string('reason')->trim()->value(),
            'assigned_user_id' => $this->integer('assigned_user_id')
                ?: ($this->filled('assigned_user_uuid')
                    ? User::query()->where('uuid', $this->string('assigned_user_uuid')->trim()->value())->value('id')
                    : null),
            'agency_id' => $this->integer('agency_id')
                ?: ($this->filled('agency_uuid')
                    ? Agency::query()->where('uuid', $this->string('agency_uuid')->trim()->value())->value('id')
                    : null),
        ];
    }
}
