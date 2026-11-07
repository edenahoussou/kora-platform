<?php

namespace App\Http\Requests;

use App\Modules\KoraPoints\Services\KoraPointAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewKoraPointVisitActionRequest extends FormRequest
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
            'operation' => ['required', 'in:validate,return'],
            'reason' => ['required_if:operation,return', 'nullable', 'string', 'min:5', 'max:2000'],
        ];
    }
}
