<?php

namespace App\Http\Requests\Settings;

use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfilePreferencesUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'push_notifications' => ['nullable', 'boolean'],
            'dispatch_alerts' => ['nullable', 'boolean'],
            'cashier_alerts' => ['nullable', 'boolean'],
            'email_notifications' => ['nullable', 'boolean'],
            'security_alerts' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', Rule::in(['fr', 'en'])],
            'theme' => ['nullable', 'string', Rule::in(['light', 'dark', 'system'])],
            'timezone' => ['nullable', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locale.in' => 'La langue doit être « fr » ou « en ».',
            'theme.in' => 'Le thème doit être « light », « dark » ou « system ».',
            'timezone.in' => 'Le fuseau horaire n\'est pas valide.',
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
