<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreKoraPartnerDocumentRequest extends FormRequest
{
    /**
     * Grants access when the actor is active and holds the related permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->status === UserStatus::Active
            && $this->user()->canAny([
                'kora-points.documents.upload',
                'kora-points.manage',
                'kora-points.remit',
                'kora-points.refund-request',
            ]);
    }

    /**
     * Returns the payload validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:102400'], 'kind' => ['required', 'in:identity,address,photo,bank_details,agreement,remittance_proof,refund_proof,other'], 'title' => ['required', 'string', 'max:255'], 'reference' => ['nullable', 'string', 'max:255'], 'signed_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after_or_equal:signed_at']];
    }
}
