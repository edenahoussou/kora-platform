<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\PricingRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PricingRule::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'line_id' => ['nullable', 'string', 'exists:lines,uuid'],
            'type' => ['required', Rule::enum(PricingRuleType::class)],
            'percentage' => ['required', 'numeric', 'min:0', 'max:1000'],
            'priority' => ['required', 'integer', 'min:1', 'max:65535'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            // Conditions structurées (CDC §3.1.3.4) : toutes optionnelles — une condition
            // non renseignée n'est pas évaluée. fill_rate en pourcentage (0-100), délai en heures.
            'conditions.fill_rate_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'conditions.fill_rate_max' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                function ($attribute, $value, $fail): void {
                    $min = $this->input('conditions.fill_rate_min');
                    if ($min !== null && $min !== '' && (float) $value < (float) $min) {
                        $fail('Le remplissage max doit être supérieur ou égal au remplissage min.');
                    }
                },
            ],
            'conditions.hours_before_departure_min' => ['nullable', 'integer', 'min:0'],
            'conditions.hours_before_departure_max' => [
                'nullable',
                'integer',
                'min:0',
                function ($attribute, $value, $fail): void {
                    $min = $this->input('conditions.hours_before_departure_min');
                    if ($min !== null && $min !== '' && (int) $value < (int) $min) {
                        $fail('Le délai max doit être supérieur ou égal au délai min.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array{line_id: int|null, type: string, conditions: array<string, mixed>, percentage: float, priority: int, starts_at: string|null, ends_at: string|null}
     */
    public function payload(): array
    {
        $lineId = null;
        if ($this->filled('line_id')) {
            $lineId = Line::query()->where('uuid', $this->string('line_id')->value())->value('id');
        }

        return [
            'line_id' => $lineId,
            'type' => $this->string('type')->value(),
            'conditions' => array_filter([
                'fill_rate_min' => $this->input('conditions.fill_rate_min'),
                'fill_rate_max' => $this->input('conditions.fill_rate_max'),
                'hours_before_departure_min' => $this->input('conditions.hours_before_departure_min'),
                'hours_before_departure_max' => $this->input('conditions.hours_before_departure_max'),
            ], fn ($value) => $value !== null && $value !== ''),
            'percentage' => (float) $this->input('percentage'),
            'priority' => (int) $this->input('priority'),
            'starts_at' => $this->filled('starts_at') ? $this->string('starts_at')->value() : null,
            'ends_at' => $this->filled('ends_at') ? $this->string('ends_at')->value() : null,
        ];
    }
}
