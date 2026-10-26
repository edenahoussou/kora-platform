<?php

namespace App\Modules\Passengers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'digits:4'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
