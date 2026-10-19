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
            'sms_notifications' => ['nullable', 'boolean'],
            'email_notifications' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', Rule::in(['fr', 'en'])],
            'theme' => ['nullable', 'string', Rule::in(['light', 'dark', 'system'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'push_notifications.boolean' => 'La valeur des notifications push doit être vraie ou fausse.',
            'whatsapp_notifications.boolean' => 'La valeur des notifications WhatsApp doit être vraie ou fausse.',
            'sms_notifications.boolean' => 'La valeur des notifications SMS doit être vraie ou fausse.',
            'email_notifications.boolean' => 'La valeur des notifications email doit être vraie ou fausse.',
            'locale.in' => 'La langue doit être « fr » ou « en ».',
            'theme.in' => 'Le thème doit être « light », « dark » ou « system ».',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return array_filter($this->validated(), fn ($value) => $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'push_notifications' => ['description' => 'Valeur de « push notifications ».'],
            'whatsapp_notifications' => ['description' => 'Valeur de « whatsapp notifications ».'],
            'sms_notifications' => ['description' => 'Valeur de « sms notifications ».'],            'email_notifications' => ['description' => 'Valeur de « email notifications ».'],
            'locale' => ['description' => 'Langue préférée.'],
            'theme' => ['description' => 'Thème d’affichage préféré.'],
        ];
    }
}
