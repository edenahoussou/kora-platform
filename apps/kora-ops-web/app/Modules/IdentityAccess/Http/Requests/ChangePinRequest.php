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
}
