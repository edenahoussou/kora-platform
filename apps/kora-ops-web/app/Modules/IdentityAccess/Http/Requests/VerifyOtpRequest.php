<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Data\OtpPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
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
            'purpose' => ['required', Rule::in(OtpPurpose::values())],
            'code' => ['required', 'digits:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'otp_challenge_uuid.required' => "L'identifiant de la demande est obligatoire.",
            'otp_challenge_uuid.uuid' => "L'identifiant de la demande n'est pas valide.",
            'purpose.required' => 'La raison de la vérification est obligatoire.',
            'purpose.in' => "La raison de la vérification n'est pas valide.",
            'code.required' => 'Le code de vérification est obligatoire.',
            'code.digits' => 'Le code doit contenir exactement 6 chiffres.',
        ];
    }
}
