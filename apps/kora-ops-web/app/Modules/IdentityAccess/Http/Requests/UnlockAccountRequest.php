<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnlockAccountRequest extends FormRequest
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
        ];
    }

    /**
     * @return array{otp_challenge_uuid: string, verification_token: string}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'otp_challenge_uuid' => (string) $validated['otp_challenge_uuid'],
            'verification_token' => (string) $validated['verification_token'],
        ];
    }
}
