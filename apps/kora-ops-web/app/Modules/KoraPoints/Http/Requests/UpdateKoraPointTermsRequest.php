<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Services\KoraPointTermsService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKoraPointTermsRequest extends FormRequest
{
    /**
     * Grants access when the actor is active and holds the related permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->status === UserStatus::Active && $this->user()->can('kora-points.terms');
    }

    /**
     * Returns the payload validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return KoraPointTermsService::rules() + ['grace_days' => ['sometimes', 'integer', 'between:0,365']];
    }
}
