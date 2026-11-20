<?php

use App\Models\User;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCharge;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Services\ParcelPricing;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

/** @return array<string, mixed> */
function pricingFixture(bool $partner = false): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
    $point = null;

    if ($partner) {
        $originAgency = Agency::factory()->create(['kind' => 'partner', 'country' => 'BJ']);
        $point = KoraPoint::factory()->create(['agency_id' => $originAgency->id]);
        $origin = ParcelHub::query()->create([
            'agency_id' => $originAgency->id,
            'kora_point_id' => $point->id,
            'kind' => ParcelHub::KIND_PARTNER_POINT,
            'stop_id' => $originStop->id,
            'enabled' => true,
            'supports_weighing' => true,
        ]);
    } else {
        $originAgency = Agency::factory()->create(['country' => 'BJ']);
        $origin = ParcelHub::query()->create([
            'agency_id' => $originAgency->id,
            'stop_id' => $originStop->id,
            'enabled' => true,
            'supports_weighing' => true,
        ]);
    }

    $destinationAgency = Agency::factory()->create(['country' => 'BJ']);
    $destination = ParcelHub::query()->create([
        'agency_id' => $destinationAgency->id,
        'stop_id' => $destinationStop->id,
        'enabled' => true,
    ]);
    $corridor = ParcelCorridor::query()->create([
        'origin_hub_id' => $origin->id,
        'destination_hub_id' => $destination->id,
        'enabled' => true,
    ]);
    $tariff = $corridor->tariffs()->create([
        'deposit_kind' => $partner ? ParcelHub::KIND_PARTNER_POINT : ParcelHub::KIND_AGENCY,
        'version' => 1,
        'base_amount' => $partner ? 2500 : 1500,
        'step_amount' => $partner ? 1000 : 1500,
        'step_grams' => 5000,
        'maximum_grams' => 30000,
        'return_amount' => 600,
        'created_by' => User::factory()->create()->id,
    ]);
    $taxRule = TaxRule::query()->create([
        'service_type' => 'parcel', 'scope' => 'BJ', 'tax_code' => 'COLIS-BJ', 'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(), 'status' => 'active',
    ]);
    $agent = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_agent');

    return compact(
        'line', 'originStop', 'destinationStop', 'originAgency', 'destinationAgency',
        'origin', 'destination', 'corridor', 'tariff', 'taxRule', 'agent', 'point',
    );
}

test('the CDC weight boundaries produce the expected amounts', function (int $grams, int $expected): void {
    $fixture = pricingFixture();

    $quote = app(ParcelPricing::class)->calculate($fixture['corridor'], $grams);

    expect($quote['total'])->toBe($expected)
        ->and($quote['currency'])->toBe('XOF')
        ->and($quote['deposit_kind'])->toBe('agency')
        ->and($quote['tariff_version'])->toBe(1)
        ->and($quote['extra_steps'])->toBe(intdiv($grams - 1, 5000));
})->with([
    'base at 5 kg' => [5000, 1500],
    'first extra gram' => [5001, 3000],
    'two 5 kg blocks' => [10000, 3000],
    'maximum 30 kg' => [30000, 9000],
]);

test('a weight above the configured maximum is refused', function (): void {
    $fixture = pricingFixture();

    expect(fn () => app(ParcelPricing::class)->calculate($fixture['corridor'], 30001))
        ->toThrow(ValidationException::class);
});

test('a partner point grid is distinct and never reuses the agency grid', function (): void {
    $fixture = pricingFixture(partner: true);

    $quote = app(ParcelPricing::class)->calculate($fixture['corridor'], 5000);

    expect($quote['deposit_kind'])->toBe('partner_point')
        ->and($quote['base_amount'])->toBe(2500)
        ->and($quote['step_amount'])->toBe(1000)
        ->and($quote['total'])->toBe(2500);
});

test('an agency corridor cannot quote from a partner point grid', function (): void {
    $fixture = pricingFixture();
    $fixture['tariff']->update(['deposit_kind' => 'partner_point']);

    expect(fn () => app(ParcelPricing::class)->calculate($fixture['corridor'], 5000))
        ->toThrow(HttpException::class);
});

test('a disabled corridor refuses quoting', function (): void {
    $fixture = pricingFixture();
    $fixture['corridor']->update(['enabled' => false]);

    $this->postJson('/api/v1/parcels/quote', [
        'corridor_uuid' => $fixture['corridor']->uuid,
        'weight_grams' => 5000,
    ])->assertStatus(409);
});

test('OPS and API expose the same public quote detail', function (): void {
    $fixture = pricingFixture();

    $api = $this->postJson('/api/v1/parcels/quote', [
        'corridor_uuid' => $fixture['corridor']->uuid,
        'weight_grams' => 5001,
    ])->assertOk()->json('data');

    $ops = $this->actingAs($fixture['agent'], 'web')
        ->postJson('/parcels/data/quote', [
            'deposit_hub_uuid' => $fixture['origin']->uuid,
            'destination_hub_uuid' => $fixture['destination']->uuid,
            'weight_grams' => 5001,
        ])->assertOk()->json('data');

    expect($ops)->toBe($api)
        ->and($api['total'])->toBe(3000)
        ->and($api['tiers'])->toHaveCount(2)
        ->and($api['estimated'])->toBeTrue();
});

test('publishing a tariff creates a new version and leaves historical charges untouched', function (): void {
    $fixture = pricingFixture();
    $parcel = Parcel::query()->create([
        'reference' => 'KC-20261120-'.Str::padLeft((string) random_int(0, 99999), 5, '0'),
        'corridor_id' => $fixture['corridor']->id,
        'deposit_hub_id' => $fixture['origin']->id,
        'selling_agency_id' => $fixture['originAgency']->id,
        'idempotency_key' => (string) Str::uuid(),
        'status' => 'weighed',
        'sender_name' => 'Pricing Sender',
        'sender_phone' => '+2290191000000',
        'recipient_name' => 'Pricing Recipient',
        'recipient_phone' => '+2290191000001',
        'recipient_phone_hash' => hash('sha256', '+2290191000001'),
        'contents' => 'Books',
        'declared_grams' => 5000,
        'weight_grams' => 5000,
        'expires_at' => now()->addDays(30),
    ]);
    $oldQuote = app(ParcelPricing::class)->calculate($fixture['corridor'], 5000);
    $charge = ParcelCharge::query()->create([
        'parcel_id' => $parcel->id,
        'tariff_id' => $oldQuote['tariff_id'],
        'tax_rule_id' => $oldQuote['tax_rule_id'],
        'total' => $oldQuote['total'],
        'net' => $oldQuote['net'],
        'tax' => $oldQuote['tax'],
        'tax_code' => $oldQuote['tax_code'],
        'tax_rate' => $oldQuote['tax_rate'],
        'fiscal_group' => $oldQuote['fiscal_group'],
    ]);

    $director = User::factory()->create()->assignRole('commercial_director');
    $this->actingAs($director, 'web')
        ->postJson("/parcels/data/corridors/{$fixture['corridor']->uuid}/tariffs", [
            'deposit_kind' => 'agency',
            'base_amount' => 2000,
            'step_amount' => 1500,
            'step_grams' => 5000,
            'maximum_grams' => 30000,
            'return_amount' => 600,
        ])->assertCreated()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.deposit_kind', 'agency');

    $newQuote = app(ParcelPricing::class)->calculate($fixture['corridor'], 5000);

    expect($charge->fresh()->total)->toBe(1500)
        ->and($newQuote['tariff_version'])->toBe(2)
        ->and($newQuote['total'])->toBe(2000);
});

test('a role without the tariff permission cannot publish a grid', function (): void {
    $fixture = pricingFixture();

    $this->actingAs($fixture['agent'], 'web')
        ->postJson("/parcels/data/corridors/{$fixture['corridor']->uuid}/tariffs", [
            'base_amount' => 2000,
            'step_amount' => 1500,
            'step_grams' => 5000,
            'maximum_grams' => 30000,
            'return_amount' => 600,
        ])->assertForbidden();
});
test('the configuration payload exposes the runtime currency and grid versions', function (): void {
    $fixture = pricingFixture();
    $director = User::factory()->create()->assignRole('operations_director');

    $this->actingAs($director, 'web')
        ->getJson('/parcels/data/configuration')
        ->assertOk()
        ->assertJsonPath('data.currency', 'XOF')
        ->assertJsonPath('data.corridors.0.origin_hub_kind', 'agency')
        ->assertJsonPath('data.corridors.0.tariffs.0.deposit_kind', 'agency')
        ->assertJsonPath('data.corridors.0.tariffs.0.version', 1);
});

test('a role without the intake permission cannot request an OPS quote', function (): void {
    $fixture = pricingFixture();
    $viewer = User::factory()->create()->assignRole('general_management');

    $this->actingAs($viewer, 'web')
        ->postJson('/parcels/data/quote', [
            'deposit_hub_uuid' => $fixture['origin']->uuid,
            'destination_hub_uuid' => $fixture['destination']->uuid,
            'weight_grams' => 5000,
        ])->assertForbidden();
});
