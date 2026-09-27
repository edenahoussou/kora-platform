<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_pin' => ['required', 'digits:4'],
            'new_pin' => ['required', 'digits:4', 'confirmed', Rule::notIn([$this->input('current_pin')])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_pin.required' => 'Le PIN actuel est obligatoire.',
            'current_pin.digits' => 'Le PIN actuel doit contenir exactement 4 chiffres.',
            'new_pin.required' => 'Le nouveau PIN est obligatoire.',
            'new_pin.digits' => 'Le nouveau PIN doit contenir exactement 4 chiffres.',
            'new_pin.confirmed' => 'La confirmation du nouveau PIN ne correspond pas.',
            'new_pin.not_in' => 'Le nouveau PIN doit être différent du PIN actuel.',
        ];
    }

    /**
     * @return array{current_pin: string, new_pin: string}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'current_pin' => (string) $validated['current_pin'],
            'new_pin' => (string) $validated['new_pin'],
        ];
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'current_pin' => ['description' => 'Code PIN actuellement défini.'],
            'new_pin' => ['description' => 'Nouveau code PIN.'],
        ];
    }
}
