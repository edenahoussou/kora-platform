<?php

namespace App\Modules\Profitability\Http\Requests;

use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Profitability\Data\EntityScope;
use App\Modules\Profitability\Data\ProfitabilityAxis;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfitabilityReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->routeIs('profitability.export') ? 'profitability.export' : 'profitability.view';

        return $this->user()?->can($permission) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['required', 'string', Rule::in(RevenuePeriod::PRESETS)],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
            'axis' => ['required', 'string', Rule::in(ProfitabilityAxis::values())],
            'detail' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', Rule::in(array_map(
                static fn (ProfitabilityAxis $type): string => $type->value,
                EntityScope::types(),
            ))],
            'entity' => [
                'nullable',
                'required_with:entity_type',
                'uuid',
                ...(($table = $this->entityTable()) !== null ? [Rule::exists($table, 'uuid')] : []),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('period')) {
            $this->merge(['period' => 'this_month']);
        }

        if (! $this->filled('axis')) {
            $this->merge(['axis' => ProfitabilityAxis::Bus->value]);
        }
    }

    /** @return array{period: string, from?: string, to?: string} */
    public function filters(): array
    {
        $validated = $this->validated();
        $filters = ['period' => (string) $validated['period']];

        if ($filters['period'] === 'custom') {
            $filters['from'] = (string) $validated['from'];
            $filters['to'] = (string) $validated['to'];
        }

        return $filters;
    }

    public function reportPeriod(): RevenuePeriod
    {
        return RevenuePeriod::fromFilters($this->filters());
    }

    public function axis(): ProfitabilityAxis
    {
        return ProfitabilityAxis::from((string) $this->validated('axis'));
    }

    public function detailKey(): ?string
    {
        $detail = $this->validated('detail');

        return is_string($detail) && $detail !== '' ? $detail : null;
    }

    public function entityScope(): ?EntityScope
    {
        $type = ProfitabilityAxis::tryFrom((string) $this->validated('entity_type'));
        $uuid = $this->validated('entity');

        if ($type === null || ! is_string($uuid) || ! in_array($type, EntityScope::types(), true)) {
            return null;
        }

        return EntityScope::resolve($type, $uuid);
    }

    private function entityTable(): ?string
    {
        return match ($this->input('entity_type')) {
            'bus' => 'buses',
            'line' => 'lines',
            'agency' => 'agencies',
            default => null,
        };
    }
}
