<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\OAuthProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OAuthTokenRequest extends FormRequest
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
            'provider' => ['required', Rule::in(OAuthProvider::values())],
            'provider_token' => ['required', 'string', 'max:4096'],
            'device_name' => ['required', 'string', 'max:120'],
            'platform' => ['required', Rule::in(DevicePlatform::values())],
            'device_fingerprint' => ['required', 'string', 'min:16', 'max:255'],
            'push_token' => ['nullable', 'string', 'max:512'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider.required' => 'Le fournisseur OAuth est obligatoire.',
            'provider.in' => 'Le fournisseur OAuth n\'est pas supporté (google, facebook, tiktok).',
            'provider_token.required' => 'Le jeton du fournisseur est obligatoire.',
            'provider_token.max' => 'Le jeton du fournisseur est trop long.',
            'device_name.required' => "Le nom de l'appareil est obligatoire.",
            'device_name.max' => "Le nom de l'appareil ne peut pas dépasser 120 caractères.",
            'platform.required' => 'La plateforme est obligatoire.',
            'platform.in' => "La plateforme n'est pas valide.",
            'device_fingerprint.required' => "L'empreinte de l'appareil est obligatoire.",
            'device_fingerprint.min' => "L'empreinte de l'appareil n'est pas valide.",
            'device_fingerprint.max' => "L'empreinte de l'appareil est trop longue.",
            'push_token.max' => 'Le token de notification est trop long.',
        ];
    }

    /**
     * @return array{provider: OAuthProvider, provider_token: string, device_name: string, platform: string, device_fingerprint: string, push_token?: string|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'provider' => OAuthProvider::from((string) $validated['provider']),
            'provider_token' => (string) $validated['provider_token'],
            'device_name' => (string) $validated['device_name'],
            'platform' => (string) $validated['platform'],
            'device_fingerprint' => (string) $validated['device_fingerprint'],
            'push_token' => isset($validated['push_token']) ? (string) $validated['push_token'] : null,
        ];
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'provider' => ['description' => 'Fournisseur OAuth ou de paiement.'],
            'provider_token' => ['description' => 'Jeton OAuth fourni par le fournisseur.'],
            'device_name' => ['description' => 'Nom de l’appareil à enregistrer.'],
            'platform' => ['description' => 'Plateforme de l’appareil ou du client.'],
            'device_fingerprint' => ['description' => 'Valeur de « device fingerprint ».'],
            'push_token' => ['description' => 'Jeton de notification push de l’appareil.'],
        ];
    }
}
