<?php

namespace App\Http\Requests;

use App\Modules\KoraPoints\Services\KoraPointAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKoraPointVisitRequest extends FormRequest
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
            'visit_type' => ['required', 'string', Rule::in(['quality', 'commercial_follow_up', 'plv_control'])],
            'score' => ['required', 'integer', 'between:0,10'],
            'report' => ['required', 'string', 'min:10', 'max:10000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'actions' => ['nullable', 'array', 'max:20'],
            'actions.*.label' => ['required_with:actions', 'string', 'min:3', 'max:255'],
            'actions.*.assignee_uuid' => ['required_with:actions', 'uuid', 'exists:users,uuid'],
            'actions.*.due_date' => ['required_with:actions', 'date', 'after_or_equal:today'],
        ];
    }
}
