<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Data\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteRegistrationRequest extends FormRequest
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
            'otp_challenge_uuid' => ['required', 'uuid'],
            'verification_token' => ['required', 'string', 'size:64'],
            'pin' => ['required', 'digits:4', 'confirmed'],
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
            'otp_challenge_uuid.required' => "L'identifiant de la vérification est obligatoire.",
            'otp_challenge_uuid.uuid' => "L'identifiant de la vérification n'est pas valide.",
            'verification_token.required' => 'Le jeton de vérification est obligatoire.',
            'verification_token.size' => "Le jeton de vérification n'est pas valide.",
            'pin.required' => 'Le PIN est obligatoire.',
            'pin.digits' => 'Le PIN doit contenir exactement 4 chiffres.',
            'pin.confirmed' => 'La confirmation du PIN ne correspond pas.',
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
     * @return array{otp_challenge_uuid: string, verification_token: string, pin: string, device_name: string, platform: string, device_fingerprint: string, push_token?: string|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'otp_challenge_uuid' => (string) $validated['otp_challenge_uuid'],
            'verification_token' => (string) $validated['verification_token'],
            'pin' => (string) $validated['pin'],
            'device_name' => (string) $validated['device_name'],
            'platform' => (string) $validated['platform'],
            'device_fingerprint' => (string) $validated['device_fingerprint'],
            'push_token' => isset($validated['push_token']) ? (string) $validated['push_token'] : null,
        ];
    }
}
