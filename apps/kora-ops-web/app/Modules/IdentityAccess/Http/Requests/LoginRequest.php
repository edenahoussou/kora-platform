<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use App\Modules\IdentityAccess\Data\DevicePlatform;
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
            'email' => ['required', 'email:rfc', 'max:255'],
            'pin' => ['required', 'digits:4'],
            'device_name' => ['required', 'string', 'max:120'],
            'platform' => ['required', Rule::in(DevicePlatform::values())],
            'device_fingerprint' => ['required', 'string', 'min:16', 'max:255'],
            'push_token' => ['nullable', 'string', 'max:512'],
            'otp_challenge_uuid' => ['nullable', 'required_with:verification_token', 'uuid'],
            'verification_token' => ['nullable', 'required_with:otp_challenge_uuid', 'string', 'size:64'],
        ];
    }

    /**
     * @return array{email: string, pin: string, device_name: string, platform: string, device_fingerprint: string, push_token?: string|null, otp_challenge_uuid?: string|null, verification_token?: string|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $payload = [
            'email' => (string) $validated['email'],
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
}
