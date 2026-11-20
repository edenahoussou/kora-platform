<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCharge;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\OperationsSettings;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function lotGUser(string $role, ?Agency $agency = null): User
{
    return User::factory()->create(['agency_id' => $agency?->id])->assignRole($role);
}

/** @return array<string, int|bool> */
function lotGParcelSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'parcelIntakeExpiryHours' => 48,
        'parcelMinimumWeightGrams' => 1,
        'parcelMaximumWeightGrams' => 30000,
        'parcelCashDeclarationThresholdFcfa' => 500000,
        'parcelFirstReminderDays' => 3,
        'parcelSecondReminderDays' => 7,
        'parcelReturnDays' => 30,
        'parcelHandoffMaxAttempts' => 5,
        'parcelFiscalAlertHours' => 20,
        'parcelRoutingHorizonDays' => 30,
        'parcelIdentityOverrideEnabled' => true,
    ], $overrides);
}

/**
 * @return array{origin: ParcelHub, destination: ParcelHub, corridor: ParcelCorridor, originAgency: Agency, destinationAgency: Agency}
 */
function lotGNetwork(bool $withTariff = true): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
    $originAgency = Agency::factory()->create(['country' => 'BJ']);
    $destinationAgency = Agency::factory()->create(['country' => 'BJ']);
    $origin = ParcelHub::query()->create([
        'agency_id' => $originAgency->id,
        'stop_id' => $originStop->id,
        'enabled' => true,
        'supports_weighing' => true,
    ]);
    $destination = ParcelHub::query()->create([
        'agency_id' => $destinationAgency->id,
        'stop_id' => $destinationStop->id,
        'enabled' => true,
        'supports_weighing' => true,
    ]);
    $corridor = ParcelCorridor::query()->create([
        'origin_hub_id' => $origin->id,
        'destination_hub_id' => $destination->id,
        'enabled' => true,
        'wallet_cash_enabled' => true,
    ]);
    if ($withTariff) {
        $corridor->tariffs()->create([
            'base_amount' => 1500,
            'return_amount' => 600,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    return compact('origin', 'destination', 'corridor', 'originAgency', 'destinationAgency', 'line');
}

function lotGParcel(array $network, string $status = 'arrived', array $overrides = []): Parcel
{
    return Parcel::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'reference' => 'KC-G-'.strtoupper(Str::random(7)),
        'corridor_id' => $network['corridor']->id,
        'deposit_hub_id' => $network['origin']->id,
        'custody_hub_id' => $status === 'withdrawn' ? null : $network['destination']->id,
        'status' => $status,
        'sender_name' => 'Lot G Sender',
        'sender_phone' => '+2290199000001',
        'recipient_name' => 'Lot G Recipient',
        'recipient_phone' => '+2290199000002',
        'recipient_phone_hash' => hash('sha256', 'lot-g-recipient'),
        'contents' => 'Documents Lot G',
        'declared_grams' => 2000,
        'weight_grams' => 2000,
        'expires_at' => now()->addDays(2),
        'available_at' => $status === 'available' ? now() : null,
        'return_due_at' => $status === 'available' ? now()->addDays(30) : null,
        'handoff_hash' => in_array($status, ['available', 'returned'], true) ? bcrypt('123456') : null,
    ], $overrides));
}

function lotGDeparture(array $network): Departure
{
    return Departure::query()->create([
        'line_id' => $network['line']->id,
        'bus_id' => Bus::query()->create([
            'registration_number' => 'LG-'.Str::random(8),
            'brand' => 'KORA',
            'model' => 'Coach',
            'seat_count' => 50,
            'status' => 'active',
        ])->id,
        'conductor_id' => User::factory()->create()->assignRole('conductor')->id,
        'departure_date' => now()->addDay()->toDateString(),
        'departure_time' => '08:00',
        'status' => DepartureStatus::Scheduled,
        'stop_times' => [
            ['stop_uuid' => $network['origin']->stop->uuid],
            ['stop_uuid' => $network['destination']->stop->uuid],
        ],
    ]);
}

it('exposes runtime parcel settings to network viewers and audits permitted changes', function (): void {
    $viewer = lotGUser('general_management');
    $ops = lotGUser('operations_director');

    $this->actingAs($viewer, 'web')
        ->getJson('/parcels/data/settings')
        ->assertOk()
        ->assertJsonPath('data.values.parcelFirstReminderDays', 3)
        ->assertJsonPath('data.values.parcelIdentityOverrideEnabled', true)
        ->assertJsonPath('data.permissions.canManage', false);

    $this->actingAs($ops, 'web')
        ->putJson('/parcels/data/settings', lotGParcelSettingsPayload([
            'parcelFirstReminderDays' => 4,
            'parcelSecondReminderDays' => 8,
            'parcelReturnDays' => 30,
            'parcelHandoffMaxAttempts' => 4,
            'parcelIdentityOverrideEnabled' => false,
        ]))
        ->assertOk()
        ->assertJsonPath('data.values.parcelFirstReminderDays', 4)
        ->assertJsonPath('data.values.parcelHandoffMaxAttempts', 4)
        ->assertJsonPath('data.values.parcelIdentityOverrideEnabled', false)
        ->assertJsonPath('data.permissions.canManage', true);

    expect(app(OperationsSettings::class)->parcelHandoffMaxAttempts)->toBe(4)
        ->and(app(OperationsSettings::class)->parcelIdentityOverrideEnabled)->toBeFalse();

    $audit = DB::table('activity_log')->where('event', 'settings.updated')->latest('id')->first();
    expect($audit)->not->toBeNull();
    $properties = json_decode((string) $audit->properties, true, flags: JSON_THROW_ON_ERROR);
    expect($properties['group'])->toBe('operations')
        ->and($properties['fields'])->toContain('parcelHandoffMaxAttempts')
        ->and($properties['fields'])->toContain('parcelIdentityOverrideEnabled')
        ->and($properties['before']['parcelHandoffMaxAttempts'])->toBe(5)
        ->and($properties['after']['parcelHandoffMaxAttempts'])->toBe(4);
});

it('rejects incoherent reminder windows and settings mutations without permission', function (): void {
    $ops = lotGUser('operations_director');
    $viewer = lotGUser('general_management');

    $this->actingAs($ops, 'web')
        ->putJson('/parcels/data/settings', lotGParcelSettingsPayload([
            'parcelFirstReminderDays' => 8,
            'parcelSecondReminderDays' => 4,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parcelSecondReminderDays');

    $this->actingAs($viewer, 'web')
        ->putJson('/parcels/data/settings', lotGParcelSettingsPayload())
        ->assertForbidden();

    expect(app(OperationsSettings::class)->parcelFirstReminderDays)->toBe(3);
});

it('applies the runtime identity policy to handoff capabilities and release', function (): void {
    $network = lotGNetwork();
    $parcel = lotGParcel($network, 'available');
    $admin = lotGUser('system_admin');

    $this->actingAs($admin, 'web')
        ->getJson("/parcels/data/{$parcel->uuid}")
        ->assertOk()
        ->assertJsonPath('data.can_override_handoff', true);

    app(OperationsSettings::class)->fill(['parcelIdentityOverrideEnabled' => false])->save();

    $this->actingAs($admin, 'web')
        ->getJson("/parcels/data/{$parcel->uuid}")
        ->assertOk()
        ->assertJsonPath('data.can_override_handoff', false);

    $this->actingAs($admin, 'web')
        ->postJson("/parcels/data/{$parcel->uuid}/release", [
            'identity_type' => 'national_id',
            'reason' => 'Destinataire sans téléphone',
        ])
        ->assertStatus(409);

    expect($parcel->refresh()->status)->toBe('available');
});

it('supervises scoped parcel counts, returns and open incidents without finance access', function (): void {
    $network = lotGNetwork();
    $otherNetwork = lotGNetwork(withTariff: false);
    $arrived = lotGParcel($network, 'arrived');
    $return = lotGParcel($network, 'return_pending');
    $arrived->incidents()->create([
        'kind' => 'damage',
        'description' => 'Emballage endommagé',
        'blocking' => true,
    ]);

    $agent = lotGUser('agency_agent', $network['originAgency']);

    $this->actingAs($agent, 'web')
        ->getJson('/parcels/data/supervision')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonPath('data.meta.awaiting_reception', 1)
        ->assertJsonPath('data.meta.returns_to_handle', 1)
        ->assertJsonPath('data.meta.open_incidents', 1)
        ->assertJsonPath('data.incidents.blocking', 1)
        ->assertJsonPath('data.finance', null)
        ->assertJsonPath('data.permissions.can_finance', false)
        ->assertJsonPath('data.by_corridor.0.count', 2)
        ->assertJsonCount(1, 'data.by_corridor')
        ->assertJsonCount(1, 'data.config_health.hubs_without_departure')
        ->assertJsonPath('data.config_health.corridors_without_tariff', [])
        ->assertJsonMissing(['label' => $otherNetwork['corridor']->originHub->stop->name]);

    $this->actingAs($agent, 'web')
        ->get('/parcels/data/supervision/export')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('keeps finance queues behind parcels.finance and reports reconciliation items', function (): void {
    $network = lotGNetwork();
    $parcel = lotGParcel($network, 'paid');
    $taxRule = TaxRule::query()->create([
        'service_type' => 'parcel',
        'scope' => 'BJ',
        'tax_code' => 'COLIS-BJ',
        'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(),
        'status' => 'active',
    ]);
    $tariff = $network['corridor']->tariffs()->firstOrFail();
    $charge = ParcelCharge::query()->create([
        'uuid' => (string) Str::uuid(),
        'parcel_id' => $parcel->id,
        'tariff_id' => $tariff->id,
        'tax_rule_id' => $taxRule->id,
        'return_leg' => false,
        'total' => 1770,
        'net' => 1500,
        'tax' => 270,
        'tax_code' => 'COLIS-BJ',
        'tax_rate' => 18,
        'fiscal_group' => 'A',
    ]);
    $payment = Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'parcel_charge_id' => $charge->id,
        'method' => 'cash',
        'provider' => 'cash',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 1770,
        'currency' => 'XOF',
        'status' => 'failed',
        'failure_code' => 'parcel_reconciliation_required',
    ]);
    FiscalInvoice::query()->create([
        'uuid' => (string) Str::uuid(),
        'parcel_charge_id' => $charge->id,
        'payment_id' => $payment->id,
        'kora_reference' => 'LG-INVOICE-1',
        'status' => FiscalInvoiceStatus::Pending,
        'total_without_tax' => 1500,
        'total_tax' => 270,
        'total_with_tax' => 1770,
        'tax_snapshot' => ['tax_code' => 'COLIS-BJ', 'tax_rate' => 18],
        'requested_at' => now(),
    ]);

    $ops = lotGUser('operations_director');
    $daf = lotGUser('daf');

    $this->actingAs($ops, 'web')
        ->getJson('/parcels/data/supervision')
        ->assertOk()
        ->assertJsonPath('data.finance', null);

    $this->actingAs($daf, 'web')
        ->getJson('/parcels/data/supervision')
        ->assertOk()
        ->assertJsonPath('data.permissions.can_finance', true)
        ->assertJsonPath('data.finance.reconciliations.count', 1)
        ->assertJsonPath('data.finance.reconciliations.items.0.parcel_reference', $parcel->reference)
        ->assertJsonPath('data.finance.pending_invoices.count', 1)
        ->assertJsonPath('data.finance.pending_refunds.count', 0);
});

it('flags hubs and corridors without compatible departures or tariffs', function (): void {
    $network = lotGNetwork(withTariff: false);
    $ops = lotGUser('operations_director');

    $this->actingAs($ops, 'web')
        ->getJson('/parcels/data/supervision')
        ->assertOk()
        ->assertJsonFragment(['code' => 'corridor_without_tariff'])
        ->assertJsonFragment(['code' => 'hub_without_departure'])
        ->assertJsonPath('data.capacity.departures_count', 0);

    $this->actingAs($ops, 'web')
        ->getJson('/parcels/data/configuration')
        ->assertOk()
        ->assertJsonPath('data.permissions.canConfigure', true);
});

it('exports aggregated supervision without personal data, pickup codes or provider payloads', function (): void {
    $network = lotGNetwork();
    $parcel = lotGParcel($network, 'withdrawn', [
        'handoff_hash' => bcrypt('654321'),
    ]);
    $ops = lotGUser('operations_director');

    $response = $this->actingAs($ops, 'web')
        ->get('/parcels/data/supervision/export')
        ->assertOk()
        ->assertDownload();

    $content = $response->streamedContent();
    expect($content)->toContain('Colis par statut')
        ->and($content)->toContain('Colis par corridor')
        ->and($content)->not->toContain('Lot G Sender')
        ->and($content)->not->toContain('+2290199000001')
        ->and($content)->not->toContain('654321')
        ->and($content)->not->toContain((string) $parcel->handoff_hash);

    $activity = DB::table('activity_log')->where('description', 'parcel_supervision_exported')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($ops->id);
});
