<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class ExecuteKoraPointRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['method' => ['required', 'in:cash,mobile_money'], 'reference' => ['required', 'string', 'max:255']];
    }
}
