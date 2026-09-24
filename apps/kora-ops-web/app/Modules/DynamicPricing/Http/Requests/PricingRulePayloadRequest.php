<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Data\FareDirection;
use App\Modules\DynamicPricing\Data\PricingRuleScope;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class PricingRulePayloadRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $needsLine = in_array($this->input('scope_type'), [PricingRuleScope::Line->value, PricingRuleScope::Segment->value], true);
        $isSegment = $this->input('scope_type') === PricingRuleScope::Segment->value;

        return [
            'scope_type' => ['required', Rule::enum(PricingRuleScope::class)],
            'line_id' => [$needsLine ? 'required' : 'nullable', 'string', 'exists:lines,uuid'],
            'origin_stop_id' => [$isSegment ? 'required' : 'nullable', 'string', 'exists:stops,uuid'],
            'destination_stop_id' => [$isSegment ? 'required' : 'nullable', 'string', 'exists:stops,uuid', 'different:origin_stop_id'],
            'direction' => ['nullable', Rule::enum(FareDirection::class)],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('scope_type') !== PricingRuleScope::Segment->value
                || ! $this->filled(['line_id', 'origin_stop_id', 'destination_stop_id'])) {
                return;
            }

            $line = Line::query()->where('uuid', $this->string('line_id')->value())->first();
            $stopUuids = [
                'origin_stop_id' => $this->string('origin_stop_id')->value(),
                'destination_stop_id' => $this->string('destination_stop_id')->value(),
            ];
            $stops = Stop::query()->whereIn('uuid', array_values($stopUuids))->get()->keyBy('uuid');

            foreach ($stopUuids as $field => $uuid) {
                $stop = $stops->get($uuid);
                if ($line && $stop && $stop->line_id !== $line->getKey()) {
                    $validator->errors()->add($field, 'Cet arrêt n’appartient pas à la ligne choisie.');
                }
            }
        });
    }

    /**
     * @return array{scope_type: string, line_id: int|null, origin_stop_id: int|null, destination_stop_id: int|null, direction: string, type: string, conditions: array<string, mixed>, percentage: float, priority: int, starts_at: string|null, ends_at: string|null}
     */
    public function payload(): array
    {
        $scope = $this->string('scope_type')->value();
        $needsLine = in_array($scope, [PricingRuleScope::Line->value, PricingRuleScope::Segment->value], true);
        $isSegment = $scope === PricingRuleScope::Segment->value;

        return [
            'scope_type' => $scope,
            'line_id' => $needsLine ? Line::query()->where('uuid', $this->string('line_id')->value())->value('id') : null,
            'origin_stop_id' => $isSegment ? Stop::query()->where('uuid', $this->string('origin_stop_id')->value())->value('id') : null,
            'destination_stop_id' => $isSegment ? Stop::query()->where('uuid', $this->string('destination_stop_id')->value())->value('id') : null,
            'direction' => $this->filled('direction') ? $this->string('direction')->value() : FareDirection::Both->value,
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
