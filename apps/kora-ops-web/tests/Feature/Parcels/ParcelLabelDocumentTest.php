<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Parcels\Actions\GenerateParcelLabelPdfAction;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Services\ParcelIntake;
use App\Modules\Parcels\Services\ParcelLabelDocument;
use App\Modules\Settings\Settings\GeneralSettings;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

/** @return array<string, mixed> */
function labelFixture(): array
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
    ]);
    $corridor = ParcelCorridor::query()->create([
        'origin_hub_id' => $origin->id,
        'destination_hub_id' => $destination->id,
        'enabled' => true,
    ]);
    $corridor->tariffs()->create([
        'base_amount' => 1500,
        'return_amount' => 600,
        'created_by' => User::factory()->create()->id,
    ]);
    TaxRule::query()->create([
        'service_type' => 'parcel',
        'scope' => 'BJ',
        'tax_code' => 'COLIS-BJ',
        'tax_rate' => 18,
        'starts_on' => now()->subDay()->toDateString(),
        'status' => 'active',
    ]);
    Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => Bus::query()->create([
            'registration_number' => 'LBL-'.Str::random(8),
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
            ['stop_uuid' => $originStop->uuid],
            ['stop_uuid' => $destinationStop->uuid],
        ],
    ]);
    $agent = User::factory()->create(['agency_id' => $originAgency->id])->assignRole('agency_agent');
    $parcel = app(ParcelIntake::class)->createForDeposit($origin, $destination, $agent, [
        'idempotency_key' => (string) Str::uuid(),
        'sender_name' => 'Label Sender',
        'sender_phone' => '+2290191000010',
        'recipient_name' => 'Label Recipient',
        'recipient_phone' => '+2290191000011',
        'contents' => 'Documents confidentiels',
        'weight_grams' => 5000,
    ], true);
    $parcel->update(['status' => 'weighed']);

    return compact('originAgency', 'destinationAgency', 'origin', 'destination', 'corridor', 'agent', 'parcel');
}

it('renders the A4 label with runtime brand, two panels, a QR and no financial data on the glued panel', function (): void {
    $fixture = labelFixture();
    $settings = app(GeneralSettings::class);
    $settings->displayName = 'KORA EXPRESS LAB';
    $settings->save();

    $response = $this->actingAs($fixture['agent'])
        ->get(route('parcels.label.show', $fixture['parcel']));

    $document = app(ParcelLabelDocument::class)->build($fixture['parcel']);
    expect($document['tracking_url'])
        ->toBe(route('api.v1.parcels.tracking', ['reference' => $fixture['parcel']->reference]))
        ->and($document['qr_svg'])->toContain('<svg');

    $response->assertOk()
        ->assertSee('KORA EXPRESS LAB')
        ->assertSee('Bordereau d’envoi')
        ->assertSee('À coller sur le colis')
        ->assertSee('Souche · ne pas coller')
        ->assertSee('Découper le bordereau')
        ->assertSee('Scanner au départ')
        ->assertSee($fixture['parcel']->reference)
        ->assertSee('Label Recipient')
        ->assertDontSee('+2290191000010')
        ->assertDontSee('+2290191000011')
        ->assertDontSee('TVA')
        ->assertDontSee('XOF')
        ->assertDontSee('1 500')
        ->assertDontSee('code fiscal');

    $event = $fixture['parcel']->events()
        ->where('kind', 'print_requested')
        ->latest('id')
        ->sole();

    expect($event->details['kind'])->toBe('label_a4')
        ->and($event->details['format'])->toBe('a4_landscape')
        ->and($event->details['channel'])->toBe('document')
        ->and($event->details['template_version'])->toBe(1);
});

it('exposes can_label to the deposit agent and hides it from a read-only role', function (): void {
    $fixture = labelFixture();

    $this->actingAs($fixture['agent'])
        ->getJson('/parcels/data')
        ->assertOk()
        ->assertJsonPath('data.0.can_label', true);

    $viewer = User::factory()->create()->assignRole('general_management');

    $this->actingAs($viewer)
        ->getJson('/parcels/data')
        ->assertOk()
        ->assertJsonPath('data.0.can_label', false);
});

it('hides and refuses the label once the parcel is cancelled', function (): void {
    $fixture = labelFixture();
    $fixture['parcel']->update(['status' => 'cancelled']);

    $this->actingAs($fixture['agent'])
        ->getJson('/parcels/data')
        ->assertOk()
        ->assertJsonPath('data.0.can_label', false);

    $this->actingAs($fixture['agent'])
        ->get(route('parcels.label.show', $fixture['parcel']))
        ->assertStatus(409);
});

it('allows the label once the parcel is registered', function (): void {
    $fixture = labelFixture();
    $fixture['parcel']->update(['status' => 'registered']);

    $this->actingAs($fixture['agent'])
        ->getJson('/parcels/data')
        ->assertOk()
        ->assertJsonPath('data.0.can_label', true);

    $this->actingAs($fixture['agent'])
        ->get(route('parcels.label.show', $fixture['parcel']))
        ->assertOk()
        ->assertSee($fixture['parcel']->reference);
});

it('does not expose the label before the parcel is accepted', function (): void {
    $fixture = labelFixture();
    $fixture['parcel']->update(['status' => 'preregistered']);

    $this->actingAs($fixture['agent'])
        ->getJson('/parcels/data')
        ->assertOk()
        ->assertJsonPath('data.0.can_label', false);

    $this->actingAs($fixture['agent'])
        ->get(route('parcels.label.show', $fixture['parcel']))
        ->assertStatus(409);
});

it('does not expose the label for a refused parcel', function (): void {
    $fixture = labelFixture();
    $fixture['parcel']->update(['status' => 'refused']);

    $this->actingAs($fixture['agent'])
        ->getJson('/parcels/data')
        ->assertOk()
        ->assertJsonPath('data.0.can_label', false);

    $this->actingAs($fixture['agent'])
        ->get(route('parcels.label.show', $fixture['parcel']))
        ->assertStatus(409);
});

it('allows an in-transit reprint at the deposit hub but refuses another agency', function (): void {
    $fixture = labelFixture();
    $fixture['parcel']->update(['status' => 'in_transit', 'custody_hub_id' => null]);

    $this->actingAs($fixture['agent'])
        ->getJson('/parcels/data')
        ->assertOk()
        ->assertJsonPath('data.0.can_label', true);

    $this->actingAs($fixture['agent'])
        ->get(route('parcels.label.show', $fixture['parcel']))
        ->assertOk();

    $foreignAgent = User::factory()->create(['agency_id' => $fixture['destinationAgency']->id])
        ->assignRole('agency_agent');

    $this->actingAs($foreignAgent)
        ->get(route('parcels.label.show', $fixture['parcel']))
        ->assertForbidden();
});

it('keeps the Phase 1 printable status matrix exact', function (): void {
    expect(ParcelLabelDocument::PRINTABLE_STATUSES)->toBe([
        'weighed',
        'registered',
        'ready',
        'assigned',
        'loaded',
        'in_transit',
        'arrived',
        'available',
    ]);

    foreach ([
        'preregistered',
        'withdrawn',
        'returned',
        'return_pending',
        'return_transit',
        'return_arrived',
        'refused',
        'cancelled',
        'cancellation_pending',
        'expired',
    ] as $status) {
        expect(ParcelLabelDocument::statusAllowsPrinting($status))->toBeFalse();
    }
});

it('refuses the label to an intake agent from another agency', function (): void {
    $fixture = labelFixture();
    $foreignAgent = User::factory()->create(['agency_id' => $fixture['destinationAgency']->id])
        ->assignRole('agency_agent');

    $this->actingAs($foreignAgent)
        ->get(route('parcels.label.show', $fixture['parcel']))
        ->assertForbidden();
});

it('returns the label PDF from the same Blade view', function (): void {
    $fixture = labelFixture();
    $action = Mockery::mock(GenerateParcelLabelPdfAction::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $action->shouldReceive('renderPdf')
        ->once()
        ->withArgs(function (string $html): bool {
            expect($html)->toContain('Bordereau d’envoi')
                ->toContain('Découper le bordereau')
                ->toContain('Label Recipient')
                ->not->toContain('+2290191000011')
                ->not->toContain('XOF');

            return true;
        })
        ->andReturn('%PDF-parcel-label');

    $this->instance(GenerateParcelLabelPdfAction::class, $action);

    $this->actingAs($fixture['agent'])
        ->get(route('parcels.label.pdf', $fixture['parcel']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="bordereau-'.$fixture['parcel']->reference.'.pdf"');
});
