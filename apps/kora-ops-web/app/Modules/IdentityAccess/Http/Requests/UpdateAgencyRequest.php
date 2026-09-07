<?php

namespace App\Modules\IdentityAccess\Http\Requests;

class UpdateAgencyRequest extends StoreAgencyRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('agency')) ?? false;
    }

    // rules() and payload() are inherited unchanged from StoreAgencyRequest:
    // the agency code is server-generated and immutable, so there is nothing
    // code-specific left to validate on update.
}
