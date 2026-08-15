<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
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
            'push_notifications' => ['nullable', 'boolean'],
            'whatsapp_notifications' => ['nullable', 'boolean'],
            'email_notifications' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', Rule::in(['fr', 'en'])],
            'theme' => ['nullable', 'string', Rule::in(['light', 'dark', 'system'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return array_filter($this->validated(), fn ($value) => $value !== null, ARRAY_FILTER_USE_BOTH);
    }
}
