<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TerminalHandshakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'max:64'],
            'hardware_model' => ['nullable', 'string', 'max:128'],
            'os_version' => ['nullable', 'string', 'max:64'],
            'app_version' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * La casse n'a pas d'importance : ResolveTerminalBySerialAction fait
     * une comparaison insensible à la casse en base. On se contente donc
     * d'un trim ici, sans supposer une convention de casse particulière.
     */
    public function serialNumber(): string
    {
        return $this->string('serial_number')->trim()->value();
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

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'serial_number' => ['description' => 'Numéro de série du terminal OPS.'],
            'hardware_model' => ['description' => 'Modèle matériel du terminal.'],
            'os_version' => ['description' => 'Version du système d’exploitation.'],
            'app_version' => ['description' => 'Version de l’application cliente.'],
        ];
    }
}
