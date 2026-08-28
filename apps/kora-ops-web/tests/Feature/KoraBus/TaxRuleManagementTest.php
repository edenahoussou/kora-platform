<?php

use App\Models\User;
use App\Modules\DynamicPricing\Models\TaxRule;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function createTaxRule(array $overrides = []): TaxRule
{
    return TaxRule::query()->create(array_merge([
        'uuid' => Str::uuid()->toString(),
        'service_type' => 'transport',
        'scope' => 'national',
        'tax_code' => 'TVA-'.Str::random(6),
        'tax_rate' => 18.0,
        'starts_on' => '2026-01-01',
        'status' => 'draft',
    ], $overrides));
}

it('lists tax rules without exposing numeric ids', function (): void {
    $rule = createTaxRule(['status' => 'active', 'tax_rate' => 18.5]);

    $this->actingAs(commercialDirector())
        ->get(route('tax-rules.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tax-rules')
            ->where('taxRules.data.0.uuid', $rule->uuid)
            ->where('taxRules.data.0.taxCode', $rule->tax_code)
            ->where('taxRules.data.0.serviceType', 'transport')
            ->where('taxRules.data.0.scope', 'national')
            ->where('taxRules.data.0.taxRate', 18.5)
            ->where('taxRules.data.0.status', 'active')
            ->missing('taxRules.data.0.id')
            ->where('permissions.canCreate', true));
});

it('creates a tax rule and rejects a duplicate tax code', function (): void {
    createTaxRule(['tax_code' => 'TVA-STD']);

    $this->actingAs(commercialDirector())->post(route('tax-rules.store'), [
        'service_type' => 'parcel',
        'scope' => 'international',
        'tax_code' => 'TVA-COLIS',
        'tax_rate' => 18,
        'starts_on' => '2026-01-01',
        'status' => 'draft',
    ])->assertRedirect(route('tax-rules.index', absolute: false));

    expect(TaxRule::query()->where('tax_code', 'TVA-COLIS')->exists())->toBeTrue();

    $this->actingAs(commercialDirector())->post(route('tax-rules.store'), [
        'service_type' => 'transport',
        'scope' => 'national',
        'tax_code' => 'TVA-STD',
        'tax_rate' => 18,
        'starts_on' => '2026-01-01',
        'status' => 'draft',
    ])->assertSessionHasErrors('tax_code');
});

it('records who validated a tax rule when it becomes active', function (): void {
    $rule = createTaxRule();
    $director = commercialDirector();

    $this->actingAs($director)
        ->patch(route('tax-rules.status', $rule), ['status' => 'active'])
        ->assertRedirect(route('tax-rules.index', absolute: false));

    $rule->refresh();
    expect($rule->status->value)->toBe('active')
        ->and($rule->validated_by)->toBe($director->id);
});

it('updates a tax rule', function (): void {
    $rule = createTaxRule();

    $this->actingAs(commercialDirector())->put(route('tax-rules.update', $rule), [
        'service_type' => $rule->service_type->value,
        'scope' => 'international',
        'tax_code' => $rule->tax_code,
        'tax_rate' => 19.5,
        'starts_on' => '2026-02-01',
        'status' => 'draft',
    ])->assertRedirect(route('tax-rules.index', absolute: false));

    $rule->refresh();
    expect($rule->scope)->toBe('international')
        ->and((float) $rule->tax_rate)->toBe(19.5);
});

it('rejects unauthorized tax rule management for agency agents', function (): void {
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)->get(route('tax-rules.index'))->assertForbidden();

    $rule = createTaxRule();
    $this->actingAs($agent)->post(route('tax-rules.store'), [
        'service_type' => 'transport',
        'scope' => 'national',
        'tax_code' => 'TVA-X',
        'tax_rate' => 18,
        'starts_on' => '2026-01-01',
        'status' => 'draft',
    ])->assertForbidden();
    $this->actingAs($agent)
        ->patch(route('tax-rules.status', $rule), ['status' => 'active'])
        ->assertForbidden();
});
