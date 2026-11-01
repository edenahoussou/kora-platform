<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class CreateKoraPointRemittanceRequest extends FormRequest
{
    /**
     * Grants access when the actor is active and holds the related permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.remit');
    }

    /**
     * Returns the payload validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['sale_uuids' => ['required', 'array', 'min:1', 'max:500'], 'sale_uuids.*' => ['required', 'uuid', 'distinct'], 'method' => ['required', 'in:cash,mobile_money,bank_transfer'], 'reference' => ['required', 'string', 'max:255']];
    }
}
