<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Models\CashSession;
use Illuminate\Foundation\Http\FormRequest;

class ApproveCashVarianceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('cashSession');

        return $session instanceof CashSession
            && ($this->user()?->can('approveVariance', $session) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
