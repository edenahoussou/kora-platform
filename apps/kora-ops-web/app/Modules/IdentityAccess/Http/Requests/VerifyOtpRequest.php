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
}
