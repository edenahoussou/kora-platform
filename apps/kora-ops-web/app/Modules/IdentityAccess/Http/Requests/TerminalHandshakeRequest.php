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
}
