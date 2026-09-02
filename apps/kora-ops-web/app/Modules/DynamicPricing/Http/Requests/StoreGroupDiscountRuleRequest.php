<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use App\Modules\DynamicPricing\Data\GroupDiscountRuleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupDiscountRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('group-discounts.manage') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(GroupDiscountRuleStatus::class)],
            'passenger_threshold' => ['required', 'integer', 'min:1'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
