<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Data\OpsNotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpsNotificationPreferencesRequest extends FormRequest
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
            'preferences' => ['required', 'array', 'min:1', 'max:'.count(OpsNotificationType::cases())],
            'preferences.*.type' => ['required', Rule::enum(OpsNotificationType::class)],
            'preferences.*.in_app' => ['sometimes', 'boolean'],
            'preferences.*.mail' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<array{type: string, in_app?: bool, mail?: bool}>
     */
    public function preferences(): array
    {
        /** @var list<array{type: string, in_app?: bool, mail?: bool}> $preferences */
        $preferences = $this->validated('preferences');

        return $preferences;
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'preferences' => ['description' => 'Préférences de notification par type.'],
            'preferences.*.type' => ['description' => 'Type de l’événement ou de la ressource.'],
            'preferences.*.in_app' => ['description' => 'Activer ou désactiver la livraison dans l’inbox.'],
            'preferences.*.mail' => ['description' => 'Activer ou désactiver la livraison par email.'],
        ];
    }
}
