<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TerminalSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('serial_number') && $this->header('X-Terminal-Serial')) {
            $this->merge(['serial_number' => $this->header('X-Terminal-Serial')]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'max:64'],
            'summary' => ['nullable', 'array'],
            'hardware_model' => ['nullable', 'string', 'max:128'],
            'os_version' => ['nullable', 'string', 'max:64'],
            'app_version' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return (array) $this->input('summary', []);
    }

    /**
     * @return array{hardware_model: string|null, os_version: string|null, app_version: string|null}
     */
    public function telemetry(): array
    {
        return [
            'hardware_model' => $this->string('hardware_model')->trim()->value() ?: null,
            'os_version' => $this->string('os_version')->trim()->value() ?: null,
            'app_version' => $this->string('app_version')->trim()->value() ?: null,
        ];
    }
}
