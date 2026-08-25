<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Models\CashSession;
use Illuminate\Foundation\Http\FormRequest;

class StoreCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashSession::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'opening_float' => ['required', 'integer', 'min:0'],
        ];
    }

    public function openingFloat(): int
    {
        return $this->integer('opening_float');
    }
}
