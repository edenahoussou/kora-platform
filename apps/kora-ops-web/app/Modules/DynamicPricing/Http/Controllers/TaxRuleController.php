<?php

namespace App\Modules\DynamicPricing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DynamicPricing\Actions\ChangeTaxRuleStatusAction;
use App\Modules\DynamicPricing\Actions\CreateTaxRuleAction;
use App\Modules\DynamicPricing\Actions\UpdateTaxRuleAction;
use App\Modules\DynamicPricing\Data\TaxRuleStatus;
use App\Modules\DynamicPricing\Data\TaxServiceType;
use App\Modules\DynamicPricing\Http\Requests\ChangeTaxRuleStatusRequest;
use App\Modules\DynamicPricing\Http\Requests\StoreTaxRuleRequest;
use App\Modules\DynamicPricing\Http\Requests\UpdateTaxRuleRequest;
use App\Modules\DynamicPricing\Models\TaxRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaxRuleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TaxRule::class);

        $query = TaxRule::query()
            ->with(['validator:id,name'])
            ->orderByDesc('starts_on');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q
                ->whereRaw('LOWER(tax_code) LIKE ?', [$term])
                ->orWhereRaw('LOWER(scope) LIKE ?', [$term]));
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, array_column(TaxRuleStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $serviceType = $request->string('service_type')->trim()->value();
        if ($serviceType !== '' && in_array($serviceType, array_column(TaxServiceType::cases(), 'value'), true)) {
            $query->where('service_type', $serviceType);
        }

        return inertia('tax-rules', [
            'taxRules' => $query->paginate(15)->through(fn (TaxRule $rule): array => $this->serializeRule($rule)),
            'statuses' => TaxRuleStatus::options(),
            'serviceTypes' => TaxServiceType::options(),
            'permissions' => [
                'canCreate' => Gate::allows('create', TaxRule::class),
                'canUpdate' => Gate::allows('update', new TaxRule),
            ],
        ]);
    }

    public function store(StoreTaxRuleRequest $request, CreateTaxRuleAction $createRule): RedirectResponse
    {
        $createRule->execute($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Taux fiscal créé.')]);

        return to_route('tax-rules.index');
    }

    public function update(UpdateTaxRuleRequest $request, TaxRule $taxRule, UpdateTaxRuleAction $updateRule): RedirectResponse
    {
        $updateRule->execute($taxRule, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Taux fiscal mis à jour.')]);

        return to_route('tax-rules.index');
    }

    public function changeStatus(
        ChangeTaxRuleStatusRequest $request,
        TaxRule $taxRule,
        ChangeTaxRuleStatusAction $changeStatus,
    ): RedirectResponse {
        $changeStatus->execute($taxRule, $request->status());

        $message = match ($request->status()) {
            TaxRuleStatus::Active => __('Taux fiscal activé.'),
            TaxRuleStatus::Archived => __('Taux fiscal archivé.'),
            TaxRuleStatus::Draft => __('Taux fiscal replacé en brouillon.'),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('tax-rules.index');
    }

    /** @return array<string, mixed> */
    private function serializeRule(TaxRule $rule): array
    {
        return [
            'uuid' => $rule->uuid,
            'serviceType' => TaxServiceType::from((string) $rule->getRawOriginal('service_type'))->value,
            'scope' => $rule->scope,
            'taxCode' => $rule->tax_code,
            'taxRate' => (float) $rule->tax_rate,
            'startsOn' => $rule->starts_on?->toDateString(),
            'endsOn' => $rule->ends_on?->toDateString(),
            'status' => TaxRuleStatus::from((string) $rule->getRawOriginal('status'))->value,
            'validatedByName' => $rule->validator?->name,
            'createdAt' => $rule->created_at?->toIso8601String(),
        ];
    }
}
