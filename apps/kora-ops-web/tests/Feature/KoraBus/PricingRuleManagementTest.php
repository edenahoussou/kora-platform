<?php

use App\Models\User;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\PricingSetting;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function createPricingRule(array $overrides = []): PricingRule
{
    return PricingRule::query()->create(array_merge([
        'uuid' => Str::uuid()->toString(),
        'line_id' => null,
        'type' => PricingRuleType::Increase->value,
        'conditions' => [],
        'percentage' => 15.0,
        'priority' => 10,
        'status' => 'draft',
    ], $overrides));
}

it('lists pricing rules without exposing numeric ids, with settings and conditions', function (): void {
    [$line] = lineWithStops();
    $rule = createPricingRule([
        'line_id' => $line->id,
        'conditions' => ['fill_rate_min' => 80, 'hours_before_departure_max' => 24],
        'percentage' => 15.5,
        'status' => 'active',
    ]);
    PricingSetting::query()->create(['floor_coefficient' => 0.5, 'ceiling_coefficient' => 2.5]);
    $this->actingAs(commercialDirector())
        ->get(route('pricing-rules.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dynamic-pricing')
            ->where('rules.data.0.uuid', $rule->uuid)
            ->where('rules.data.0.lineCode', $line->code)
            ->where('rules.data.0.type', 'increase')
            ->where('rules.data.0.percentage', 15.5)
            ->where('rules.data.0.priority', 10)
            ->where('rules.data.0.conditions.fillRateMin', 80)
            ->where('rules.data.0.conditions.hoursBeforeDepartureMax', 24)
            ->where('rules.data.0.status', 'active')
            ->missing('rules.data.0.id')
            ->missing('rules.data.0.line_id')
            ->where('settings.floorCoefficient', 0.5)
            ->where('settings.ceilingCoefficient', 2.5)
            ->where('permissions.canCreate', true));
});

it('creates a pricing rule in draft status regardless of submitted status', function (): void {
    [$line] = lineWithStops();

    $this->actingAs(commercialDirector())->post(route('pricing-rules.store'), [
        'line_id' => $line->uuid,
        'type' => 'increase',
        'percentage' => 20,
        'priority' => 5,
        'conditions' => [
            'fill_rate_min' => 80,
            'hours_before_departure_max' => 24,
        ],
    ])->assertRedirect(route('pricing-rules.index', absolute: false));

    $rule = PricingRule::query()->where('line_id', $line->id)->firstOrFail();
    expect($rule->status->value)->toBe('draft')
        ->and((float) $rule->percentage)->toBe(20.0)
        ->and($rule->conditions)->toBe(['fill_rate_min' => 80, 'hours_before_departure_max' => 24]);
});

it('rejects an inverted fill rate range on a pricing rule', function (): void {
    $this->actingAs(commercialDirector())->post(route('pricing-rules.store'), [
        'type' => 'increase',
        'percentage' => 20,
        'priority' => 5,
        'conditions' => ['fill_rate_min' => 80, 'fill_rate_max' => 30],
    ])->assertSessionHasErrors('conditions.fill_rate_max');
});

it('updates a pricing rule', function (): void {
    $rule = createPricingRule();

    $this->actingAs(commercialDirector())->put(route('pricing-rules.update', $rule), [
        'type' => 'decrease',
        'percentage' => 25,
        'priority' => 3,
        'conditions' => ['fill_rate_max' => 30, 'hours_before_departure_min' => 168],
    ])->assertRedirect(route('pricing-rules.index', absolute: false));

    $rule->refresh();
    expect($rule->type->value)->toBe('decrease')
        ->and((int) $rule->priority)->toBe(3)
        ->and($rule->conditions)->toBe(['fill_rate_max' => 30, 'hours_before_departure_min' => 168]);
});

it('activates and archives a pricing rule via status change', function (): void {
    $rule = createPricingRule();

    $this->actingAs(commercialDirector())
        ->patch(route('pricing-rules.status', $rule), ['status' => 'active'])
        ->assertRedirect(route('pricing-rules.index', absolute: false));
    expect($rule->refresh()->status->value)->toBe('active');

    $this->actingAs(commercialDirector())
        ->patch(route('pricing-rules.status', $rule), ['status' => 'archived'])
        ->assertRedirect(route('pricing-rules.index', absolute: false));
    expect($rule->refresh()->status->value)->toBe('archived');
});

it('updates the global floor and ceiling settings', function (): void {
    $this->actingAs(commercialDirector())->patch(route('pricing-rules.settings'), [
        'floor_coefficient' => 0.6,
        'ceiling_coefficient' => 1.8,
    ])->assertRedirect(route('pricing-rules.index', absolute: false));

    $setting = PricingSetting::current();
    expect((float) $setting->floor_coefficient)->toBe(0.6)
        ->and((float) $setting->ceiling_coefficient)->toBe(1.8)
        ->and($setting->updated_by)->not->toBeNull();
});

it('rejects a floor greater than the ceiling', function (): void {
    $this->actingAs(commercialDirector())->patch(route('pricing-rules.settings'), [
        'floor_coefficient' => 1.5,
        'ceiling_coefficient' => 1.0,
    ])->assertSessionHasErrors('floor_coefficient');
});

it('rejects unauthorized pricing rule management for agency agents', function (): void {
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)->get(route('pricing-rules.index'))->assertForbidden();

    $rule = createPricingRule();
    $this->actingAs($agent)->post(route('pricing-rules.store'), [
        'type' => 'increase',
        'percentage' => 10,
        'priority' => 1,
    ])->assertForbidden();
    $this->actingAs($agent)->put(route('pricing-rules.update', $rule), [
        'type' => 'increase',
        'percentage' => 10,
        'priority' => 1,
    ])->assertForbidden();
    $this->actingAs($agent)
        ->patch(route('pricing-rules.status', $rule), ['status' => 'active'])
        ->assertForbidden();
    $this->actingAs($agent)
        ->patch(route('pricing-rules.settings'), ['floor_coefficient' => 0.5])
        ->assertForbidden();
});
