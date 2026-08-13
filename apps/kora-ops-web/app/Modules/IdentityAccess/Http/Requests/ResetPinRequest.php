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
}
