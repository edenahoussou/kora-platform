<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdatePricingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pricing.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'floor_coefficient' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'ceiling_coefficient' => ['nullable', 'numeric', 'min:1', 'max:10'],
        ];
    }

    /**
     * Cross-field check done manually (rather than a `lte:` rule) because both
     * fields are independently nullable and `lte` misbehaves when one is empty.
     * Throws directly rather than mutating the validator's error bag after the
     * fact — passedValidation() runs only once validation already succeeded,
     * so silently appending to errors() here would have no effect.
     */
    protected function passedValidation(): void
    {
        $floor = $this->input('floor_coefficient');
        $ceiling = $this->input('ceiling_coefficient');

        if ($floor !== null && $floor !== '' && $ceiling !== null && $ceiling !== '' && (float) $floor > (float) $ceiling) {
            throw ValidationException::withMessages([
                'floor_coefficient' => __('Le plancher doit être inférieur ou égal au plafond.'),
            ]);
        }
    }

    /** @return array{floor_coefficient: float|null, ceiling_coefficient: float|null} */
    public function payload(): array
    {
        return [
            'floor_coefficient' => $this->filled('floor_coefficient') ? (float) $this->input('floor_coefficient') : null,
            'ceiling_coefficient' => $this->filled('ceiling_coefficient') ? (float) $this->input('ceiling_coefficient') : null,
        ];
    }
}
