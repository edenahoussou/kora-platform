<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class RequestKoraPointRefundRequest extends FormRequest
{
    /**
     * Grants access when the actor is active and holds the related permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.refund-request');
    }

    /**
     * Returns the payload validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000'], 'passenger_uuid' => ['nullable', 'uuid']];
    }
}
