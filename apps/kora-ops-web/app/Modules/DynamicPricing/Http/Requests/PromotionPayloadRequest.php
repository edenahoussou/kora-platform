<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Data\PromotionBenefitType;
use App\Modules\DynamicPricing\Data\PromotionChannel;
use App\Modules\DynamicPricing\Data\PromotionScope;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

abstract class PromotionPayloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pricing.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => Str::upper(trim((string) $this->input('code')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $isChannel = $this->input('scope_type') === PromotionScope::Channel->value;
        $needsLine = in_array($this->input('scope_type'), [PromotionScope::Line->value, PromotionScope::Segment->value], true);
        $isSegment = $this->input('scope_type') === PromotionScope::Segment->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'regex:/^[A-Z0-9\-]{3,32}$/', $this->codeUniqueRule()],
            'benefit_type' => ['required', Rule::enum(PromotionBenefitType::class)],
            'benefit_value' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail): void {
                    if ($this->input('benefit_type') === PromotionBenefitType::Percentage->value
                        && ((float) $value <= 0 || (float) $value > 100)) {
                        $fail('Le pourcentage de remise doit être compris entre 0 et 100.');
                    }
                    if ($this->input('benefit_type') === PromotionBenefitType::FixedAmount->value
                        && (float) $value < 1) {
                        $fail('Le montant de remise doit être d’au moins 1 FCFA.');
                    }
                },
            ],
            'scope_type' => ['required', Rule::enum(PromotionScope::class)],
            'channel' => [
                $isChannel ? 'required' : 'nullable',
                Rule::enum(PromotionChannel::class),
            ],
            'line_id' => [
                $needsLine ? 'required' : 'nullable',
                'string',
                'exists:lines,uuid',
            ],
            'origin_stop_id' => [
                $isSegment ? 'required' : 'nullable',
                'string',
                'exists:stops,uuid',
            ],
            'destination_stop_id' => [
                $isSegment ? 'required' : 'nullable',
                'string',
                'exists:stops,uuid',
                'different:origin_stop_id',
            ],
            'priority' => ['required', 'integer', 'min:1', 'max:65535'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'force' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('scope_type') !== PromotionScope::Segment->value
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

    abstract protected function codeUniqueRule(): Unique;

    /**
     * @return array{name: string, code: string, benefit_type: string, benefit_value: float, scope_type: string, channel: string|null, line_id: int|null, origin_stop_id: int|null, destination_stop_id: int|null, priority: int, starts_at: string, ends_at: string|null, max_uses: int|null}
     */
    public function payload(): array
    {
        $scope = $this->string('scope_type')->value();
        $needsLine = in_array($scope, [PromotionScope::Line->value, PromotionScope::Segment->value], true);
        $isSegment = $scope === PromotionScope::Segment->value;

        return [
            'name' => $this->string('name')->trim()->value(),
            'code' => $this->string('code')->value(),
            'benefit_type' => $this->string('benefit_type')->value(),
            'benefit_value' => (float) $this->input('benefit_value'),
            'scope_type' => $scope,
            'channel' => $scope === PromotionScope::Channel->value ? $this->string('channel')->value() : null,
            'line_id' => $needsLine ? Line::query()->where('uuid', $this->string('line_id')->value())->value('id') : null,
            'origin_stop_id' => $isSegment ? Stop::query()->where('uuid', $this->string('origin_stop_id')->value())->value('id') : null,
            'destination_stop_id' => $isSegment ? Stop::query()->where('uuid', $this->string('destination_stop_id')->value())->value('id') : null,
            'priority' => (int) $this->input('priority'),
            'starts_at' => $this->string('starts_at')->value(),
            'ends_at' => $this->filled('ends_at') ? $this->string('ends_at')->value() : null,
            'max_uses' => $this->filled('max_uses') ? (int) $this->input('max_uses') : null,
        ];
    }
}
