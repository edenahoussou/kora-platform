<?php

namespace App\Modules\DynamicPricing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Actions\ChangePricingRuleStatusAction;
use App\Modules\DynamicPricing\Actions\CreatePricingRuleAction;
use App\Modules\DynamicPricing\Actions\UpdatePricingRuleAction;
use App\Modules\DynamicPricing\Actions\UpdatePricingSettingAction;
use App\Modules\DynamicPricing\Data\FareDirection;
use App\Modules\DynamicPricing\Data\PricingRuleScope;
use App\Modules\DynamicPricing\Data\PricingRuleStatus;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Http\Requests\ChangePricingRuleStatusRequest;
use App\Modules\DynamicPricing\Http\Requests\StorePricingRuleRequest;
use App\Modules\DynamicPricing\Http\Requests\UpdatePricingRuleRequest;
use App\Modules\DynamicPricing\Http\Requests\UpdatePricingSettingRequest;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\PricingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PricingRuleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PricingRule::class);

        $query = PricingRule::query()
            ->with(['line:id,uuid,code,name', 'originStop:id,uuid,name', 'destinationStop:id,uuid,name'])
            ->orderBy('priority');

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, array_column(PricingRuleStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $scope = $request->string('scope')->trim()->value();
        if ($scope !== '' && in_array($scope, PricingRuleScope::values(), true)) {
            $query->where('scope_type', $scope);
        }

        $lineId = $request->string('line')->trim()->value();
        if ($lineId !== '') {
            $line = Line::query()->where('uuid', $lineId)->first();
            if ($line) {
                $query->where('line_id', $line->id);
            }
        }

        $setting = PricingSetting::current();

        return inertia('dynamic-pricing', [
            'rules' => $query->paginate(15)->through(fn (PricingRule $rule): array => $this->serializeRule($rule)),
            'statuses' => PricingRuleStatus::options(),
            'types' => PricingRuleType::options(),
            'scopes' => PricingRuleScope::options(),
            'lines' => Line::query()
                ->with('stops:id,line_id,uuid,name,stop_order')
                ->orderBy('code')
                ->get()
                ->map(fn (Line $line): array => [
                    'uuid' => $line->uuid,
                    'code' => $line->code,
                    'name' => $line->name,
                    'stops' => $line->stops->map(fn (Stop $stop): array => [
                        'uuid' => $stop->uuid,
                        'name' => $stop->name,
                        'stopOrder' => $stop->stop_order,
                    ])->values(),
                ]),
            'settings' => [
                'floorCoefficient' => $setting?->floor_coefficient !== null ? (float) $setting->floor_coefficient : null,
                'ceilingCoefficient' => $setting?->ceiling_coefficient !== null ? (float) $setting->ceiling_coefficient : null,
                'updatedAt' => $setting?->updated_at?->toIso8601String(),
            ],
            'permissions' => [
                'canCreate' => Gate::allows('create', PricingRule::class),
                'canUpdate' => Gate::allows('update', new PricingRule),
                'canManageSettings' => $request->user()?->can('pricing.manage') ?? false,
            ],
        ]);
    }

    public function store(StorePricingRuleRequest $request, CreatePricingRuleAction $createRule): RedirectResponse
    {
        $createRule->execute($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Règle de pricing créée en brouillon.')]);

        return to_route('pricing-rules.index');
    }

    public function update(UpdatePricingRuleRequest $request, PricingRule $pricingRule, UpdatePricingRuleAction $updateRule): RedirectResponse
    {
        $updateRule->execute($pricingRule, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Règle de pricing mise à jour.')]);

        return to_route('pricing-rules.index');
    }

    public function changeStatus(
        ChangePricingRuleStatusRequest $request,
        PricingRule $pricingRule,
        ChangePricingRuleStatusAction $changeStatus,
    ): RedirectResponse {
        $changeStatus->execute($pricingRule, $request->status());

        $message = match ($request->status()) {
            PricingRuleStatus::Active => __('Règle activée. Effet immédiat sur les nouvelles recherches.'),
            PricingRuleStatus::Archived => __('Règle archivée.'),
            PricingRuleStatus::Draft => __('Règle replacée en brouillon.'),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('pricing-rules.index');
    }

    public function updateSettings(UpdatePricingSettingRequest $request, UpdatePricingSettingAction $updateSettings): RedirectResponse
    {
        $updateSettings->execute($request->payload(), $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plancher/plafond mis à jour.')]);

        return to_route('pricing-rules.index');
    }

    /** @return array<string, mixed> */
    private function serializeRule(PricingRule $rule): array
    {
        $conditions = $rule->conditions ?? [];

        return [
            'uuid' => $rule->uuid,
            'scope' => ($rule->scope_type ?? $rule->effectiveScope())->value,
            'direction' => ($rule->direction ?? FareDirection::Both)->value,
            'lineUuid' => $rule->line?->uuid,
            'lineCode' => $rule->line?->code,
            'lineName' => $rule->line?->name,
            'originStopUuid' => $rule->originStop?->uuid,
            'originStopName' => $rule->originStop?->name,
            'destinationStopUuid' => $rule->destinationStop?->uuid,
            'destinationStopName' => $rule->destinationStop?->name,
            'type' => PricingRuleType::from((string) $rule->getRawOriginal('type'))->value,
            'percentage' => (float) $rule->percentage,
            'priority' => (int) $rule->priority,
            'conditions' => [
                'fillRateMin' => $conditions['fill_rate_min'] ?? null,
                'fillRateMax' => $conditions['fill_rate_max'] ?? null,
                'hoursBeforeDepartureMin' => $conditions['hours_before_departure_min'] ?? null,
                'hoursBeforeDepartureMax' => $conditions['hours_before_departure_max'] ?? null,
            ],
            'startsAt' => $rule->starts_at?->toIso8601String(),
            'endsAt' => $rule->ends_at?->toIso8601String(),
            'status' => PricingRuleStatus::from((string) $rule->getRawOriginal('status'))->value,
            'createdAt' => $rule->created_at?->toIso8601String(),
        ];
    }
}
