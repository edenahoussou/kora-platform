<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class RequestKoraPointRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.refund-request');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000'], 'passenger_uuid' => ['nullable', 'uuid']];
    }
}
