<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use App\Modules\DynamicPricing\Data\TaxRuleStatus;
use App\Modules\DynamicPricing\Data\TaxServiceType;
use App\Modules\DynamicPricing\Models\TaxRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('taxRule')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var TaxRule|null $taxRule */
        $taxRule = $this->route('taxRule');

        return [
            'service_type' => ['required', Rule::enum(TaxServiceType::class)],
            'scope' => ['required', 'string', 'max:32'],
            'tax_code' => ['required', 'string', 'max:64', Rule::unique('tax_rules', 'tax_code')->ignore($taxRule?->id)],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::enum(TaxRuleStatus::class)],
        ];
    }

    /**
     * @return array{service_type: string, scope: string, tax_code: string, tax_rate: float, starts_on: string, ends_on: string|null, status: string}
     */
    public function payload(): array
    {
        return [
            'service_type' => $this->string('service_type')->value(),
            'scope' => $this->string('scope')->trim()->value(),
            'tax_code' => $this->string('tax_code')->trim()->value(),
            'tax_rate' => (float) $this->input('tax_rate'),
            'starts_on' => $this->string('starts_on')->value(),
            'ends_on' => $this->filled('ends_on') ? $this->string('ends_on')->value() : null,
            'status' => $this->string('status')->value(),
        ];
    }
}
