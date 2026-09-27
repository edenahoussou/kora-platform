<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Modules\Finance\Data\RevenuePeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    /** @var list<string> */
    private const PERIODS = ['today', 'this_week', 'this_month', 'custom'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['required', 'string', Rule::in(self::PERIODS)],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('period')) {
            $this->merge(['period' => 'today']);
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

    public function dashboardPeriod(): RevenuePeriod
    {
        return RevenuePeriod::fromFilters($this->filters());
    }
}
