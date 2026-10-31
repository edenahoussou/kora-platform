<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class ChangeKoraPointStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', 'in:active,suspended,closed']];
    }
}
