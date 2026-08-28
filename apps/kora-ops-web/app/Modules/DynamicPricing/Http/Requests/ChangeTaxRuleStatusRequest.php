<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use App\Modules\DynamicPricing\Data\TaxRuleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTaxRuleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('taxRule')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(TaxRuleStatus::class)]];
    }

    public function status(): TaxRuleStatus
    {
        return TaxRuleStatus::from($this->string('status')->trim()->value());
    }
}
