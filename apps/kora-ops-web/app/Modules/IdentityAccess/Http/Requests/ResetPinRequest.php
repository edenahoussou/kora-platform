<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPinRequest extends FormRequest
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
            'pin.required' => 'Le nouveau PIN est obligatoire.',
            'pin.digits' => 'Le PIN doit contenir exactement 4 chiffres.',
            'pin.confirmed' => 'La confirmation du PIN ne correspond pas.',
        ];
    }

    /**
     * @return array{otp_challenge_uuid: string, verification_token: string, pin: string}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'otp_challenge_uuid' => (string) $validated['otp_challenge_uuid'],
            'verification_token' => (string) $validated['verification_token'],
            'pin' => (string) $validated['pin'],
        ];
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'otp_challenge_uuid' => ['description' => 'UUID du challenge OTP.'],
            'verification_token' => ['description' => 'Jeton de vérification à usage unique.'],
            'pin' => ['description' => 'Code PIN du compte.'],
        ];
    }
}
