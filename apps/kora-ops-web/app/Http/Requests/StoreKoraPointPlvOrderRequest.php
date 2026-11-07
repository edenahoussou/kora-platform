<?php

namespace App\Http\Requests;

use App\Modules\KoraPoints\Services\KoraPointAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKoraPointPlvOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        app(KoraPointAccess::class)->authorizeInternal($this->user(), 'kora-points.manage');

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'support' => ['required', 'string', Rule::in(['affiches_a3', 'autocollants', 'bache', 'panneau'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
