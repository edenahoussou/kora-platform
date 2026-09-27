<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Services\AuthIdentifierResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
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
            'identifier' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'email' => ['nullable', 'email:rfc', 'max:255', 'required_without:identifier'],
            'pin' => ['required', 'digits:4'],
            'device_name' => ['required', 'string', 'max:120'],
            'platform' => ['required', Rule::in(DevicePlatform::values())],
            'device_fingerprint' => ['required', 'string', 'min:16', 'max:255'],
            'push_token' => ['nullable', 'string', 'max:512'],
            'otp_challenge_uuid' => ['nullable', 'required_with:verification_token', 'uuid'],
            'verification_token' => ['nullable', 'required_with:otp_challenge_uuid', 'string', 'size:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $identifier = $this->input('identifier', $this->input('email'));

            if ($this->filled('identifier') && $this->filled('email')) {
                $validator->errors()->add('identifier', 'Utilisez identifier ou email, mais pas les deux.');
            }

            if (is_string($identifier) && ! resolve(AuthIdentifierResolver::class)->isValidIdentifier($identifier)) {
                $validator->errors()->add('identifier', "L'email ou le numéro de téléphone n'est pas valide.");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identifier.required_without' => "L'email ou le numéro de téléphone est obligatoire.",
            'identifier.max' => "L'identifiant ne peut pas dépasser 255 caractères.",
            'email.required_without' => "L'email ou le numéro de téléphone est obligatoire.",
            'email.email' => 'Veuillez saisir une adresse email valide.',
            'email.max' => "L'adresse email ne peut pas dépasser 255 caractères.",
            'pin.required' => 'Le PIN est obligatoire.',
            'pin.digits' => 'Le PIN doit contenir exactement 4 chiffres.',
            'device_name.required' => "Le nom de l'appareil est obligatoire.",
            'device_name.max' => "Le nom de l'appareil ne peut pas dépasser 120 caractères.",
            'platform.required' => 'La plateforme est obligatoire.',
            'platform.in' => "La plateforme n'est pas valide.",
            'device_fingerprint.required' => "L'empreinte de l'appareil est obligatoire.",
            'device_fingerprint.min' => "L'empreinte de l'appareil n'est pas valide.",
            'device_fingerprint.max' => "L'empreinte de l'appareil est trop longue.",
            'push_token.max' => 'Le token de notification est trop long.',
            'otp_challenge_uuid.required_with' => "L'identifiant de vérification est requis avec le jeton.",
            'otp_challenge_uuid.uuid' => "L'identifiant de vérification n'est pas valide.",
            'verification_token.required_with' => "Le jeton de vérification est requis avec l'identifiant.",
            'verification_token.size' => "Le jeton de vérification n'est pas valide.",
        ];
    }

    /**
     * @return array{identifier: string, pin: string, device_name: string, platform: string, device_fingerprint: string, push_token?: string|null, otp_challenge_uuid?: string|null, verification_token?: string|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $payload = [
            'identifier' => (string) ($validated['identifier'] ?? $validated['email']),
            'pin' => (string) $validated['pin'],
            'device_name' => (string) $validated['device_name'],
            'platform' => (string) $validated['platform'],
            'device_fingerprint' => (string) $validated['device_fingerprint'],
            'push_token' => isset($validated['push_token']) ? (string) $validated['push_token'] : null,
        ];

        if (isset($validated['otp_challenge_uuid'])) {
            $payload['otp_challenge_uuid'] = (string) $validated['otp_challenge_uuid'];
        }

        if (isset($validated['verification_token'])) {
            $payload['verification_token'] = (string) $validated['verification_token'];
        }

        return $payload;
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'identifier' => ['description' => 'Identifiant de connexion, sous forme d’email ou de téléphone.'],
            'email' => ['description' => 'Adresse email.'],
            'pin' => ['description' => 'Code PIN du compte.'],
            'device_name' => ['description' => 'Nom de l’appareil à enregistrer.'],
            'platform' => ['description' => 'Plateforme de l’appareil ou du client.'],
            'device_fingerprint' => ['description' => 'Valeur de « device fingerprint ».'],
            'push_token' => ['description' => 'Jeton de notification push de l’appareil.'],
            'otp_challenge_uuid' => ['description' => 'UUID du challenge OTP.'],
            'verification_token' => ['description' => 'Jeton de vérification à usage unique.'],
        ];
    }
}
