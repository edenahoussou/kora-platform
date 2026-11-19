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
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[\d\s\-.()]{6,20}$/'],
            'country_code' => ['nullable', 'string', 'max:4', 'regex:/^\+?[\d\s\-.()]{1,7}$/'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'referral_code' => ['nullable', 'string', 'max:24'],
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
            'name.max' => 'Le nom ne peut pas dépasser 120 caractères.',
            'email.email' => 'Veuillez saisir une adresse email valide.',
            'email.max' => "L'adresse email ne peut pas dépasser 255 caractères.",
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 20 caractères.',
            'phone.regex' => 'Le numéro de téléphone doit être au format international (ex: +2290191003606) ou local (ex: 0191003606).',
            'country_code.max' => "L'indicatif pays ne peut pas dépasser 4 caractères.",
            'country_code.regex' => "L'indicatif pays doit contenir uniquement des chiffres (ex: +229).",
            'birth_date.date' => "La date de naissance n'est pas valide.",
            'referral_code.string' => 'Le code de parrainage doit être une chaîne de caractères.',
            'referral_code.max' => 'Le code de parrainage ne peut pas dépasser 24 caractères.',
            'birth_date.before' => "La date de naissance doit être antérieure à aujourd'hui.",
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
     * @return array{otp_challenge_uuid: string, verification_token: string, pin: string, device_name: string, platform: string, device_fingerprint: string, push_token?: string|null, name?: string|null, email?: string|null, phone?: string|null, country_code?: string|null, birth_date?: string|null, referral_code?: string|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'otp_challenge_uuid' => (string) $validated['otp_challenge_uuid'],
            'verification_token' => (string) $validated['verification_token'],
            'pin' => (string) $validated['pin'],
            'referral_code' => $validated['referral_code'] ?? null,
            'device_name' => (string) $validated['device_name'],
            'platform' => (string) $validated['platform'],
            'device_fingerprint' => (string) $validated['device_fingerprint'],
            'push_token' => isset($validated['push_token']) ? (string) $validated['push_token'] : null,
            'name' => isset($validated['name']) ? (string) $validated['name'] : null,
            'email' => isset($validated['email']) ? (string) $validated['email'] : null,
            'phone' => isset($validated['phone']) ? (string) $validated['phone'] : null,
            'country_code' => isset($validated['country_code']) ? (string) $validated['country_code'] : null,
            'birth_date' => isset($validated['birth_date']) ? (string) $validated['birth_date'] : null,
        ];
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'otp_challenge_uuid' => ['description' => 'UUID du challenge OTP.'],
            'verification_token' => [
                'description' => 'Jeton de vérification à usage unique.',
                'example' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
            ],
            'pin' => ['description' => 'Code PIN du compte.'],
            'name' => ['description' => 'Nom affiché ou nom de l’appareil.'],
            'email' => ['description' => 'Adresse email.'],
            'phone' => ['description' => 'Numéro de téléphone.'],
            'country_code' => ['description' => 'Indicatif téléphonique du pays.'],
            'birth_date' => ['description' => 'Date de naissance.', 'example' => '1990-05-15'],
            'referral_code' => ['description' => 'Code facultatif réservé à la création d’un nouveau compte voyageur.', 'example' => 'KA1B2C3D'],
            'device_name' => ['description' => 'Nom de l’appareil à enregistrer.'],
            'platform' => ['description' => 'Plateforme de l’appareil ou du client.'],
            'device_fingerprint' => ['description' => 'Valeur de « device fingerprint ».'],
            'push_token' => ['description' => 'Jeton de notification push de l’appareil.'],
        ];
    }
}
