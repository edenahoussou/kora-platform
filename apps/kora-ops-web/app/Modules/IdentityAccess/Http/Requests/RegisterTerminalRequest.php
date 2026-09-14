<?php

namespace App\Modules\IdentityAccess\Http\Requests;

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

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'max:64', 'unique:terminals_p501,serial_number'],
            'agency_id' => ['nullable', 'integer', Rule::exists((new Agency)->getTable(), 'id')],
        ];
    }

    /**
     * @return array{serial_number: string, agency_id: int|null}
     */
    public function payload(): array
    {
        return [
            'serial_number' => $this->string('serial_number')->trim()->upper()->value(),
            'agency_id' => $this->integer('agency_id') ?: null,
        ];
    }
}
