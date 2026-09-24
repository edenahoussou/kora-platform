<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Data\TerminalType;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TerminalP501::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('serial_number')) {
            $this->merge([
                'serial_number' => $this->string('serial_number')->trim()->upper()->value(),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'max:64', 'unique:terminals_p501,serial_number'],
            'type' => ['nullable', Rule::enum(TerminalType::class)],
            'agency_id' => ['nullable', 'integer', Rule::exists((new Agency)->getTable(), 'id')],
            'agency_uuid' => ['nullable', 'uuid', Rule::exists((new Agency)->getTable(), 'uuid')],
        ];
    }

    /**
     * @return array{serial_number: string, type: TerminalType, agency_id: int|null}
     */
    public function payload(): array
    {
        return [
            'serial_number' => $this->string('serial_number')->trim()->upper()->value(),
            'type' => $this->filled('type') ? TerminalType::from($this->string('type')->value()) : TerminalType::P501,
            'agency_id' => $this->integer('agency_id')
                ?: ($this->filled('agency_uuid')
                    ? Agency::query()->where('uuid', $this->string('agency_uuid')->trim()->value())->value('id')
                    : null),
        ];
    }
}
