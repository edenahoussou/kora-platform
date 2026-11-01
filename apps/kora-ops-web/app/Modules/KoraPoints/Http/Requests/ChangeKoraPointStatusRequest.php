<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class ChangeKoraPointStatusRequest extends FormRequest
{
    /**
     * Grants access when the actor is active and holds the related permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.manage');
    }

    /**
     * Returns the payload validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['status' => ['required', 'in:active,suspended,closed']];
    }
}
