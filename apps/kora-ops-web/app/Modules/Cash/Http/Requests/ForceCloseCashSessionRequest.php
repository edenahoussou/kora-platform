<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Models\CashSession;
use Illuminate\Foundation\Http\FormRequest;

class ForceCloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('cashSession');

        return $session instanceof CashSession
            && ($this->user()?->can('forceClose', $session) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
