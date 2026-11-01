<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class ReceiveKoraPointRemittanceRequest extends FormRequest
{
    /**
     * Grants access when the actor is active and holds the related permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.finance');
    }

    /**
     * Returns the payload validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['received_amount' => ['required', 'integer', 'min:0', 'max:1000000000000'], 'reject' => ['sometimes', 'boolean'], 'note' => ['nullable', 'string', 'max:2000']];
    }
}
