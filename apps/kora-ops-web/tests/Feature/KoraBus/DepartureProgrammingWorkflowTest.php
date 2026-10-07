<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Models\DepartureProgramme;
use App\Modules\Departures\Models\DepartureTemplateVersion;
use App\Modules\Departures\Models\PlannedDeparture;
use App\Modules\DynamicPricing\Models\Fare;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 09:00:00'));
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->actor = User::factory()->create();
    $this->actor->assignRole('operations_director');
    $this->line = Line::query()->create(['code' => 'COT-LOM', 'name' => 'Cotonou → Lomé', 'type' => 'internationale', 'status' => 'active', 'estimated_duration_minutes' => 180]);
    $firstPhysical = PhysicalStop::query()->create(['uuid' => (string) Str::uuid(), 'name' => 'Cotonou DP', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $lastPhysical = PhysicalStop::query()->create(['uuid' => (string) Str::uuid(), 'name' => 'Lomé DP', 'city' => 'Lomé', 'country' => 'TG', 'status' => 'active']);
    $this->firstStop = $this->line->stops()->create(['uuid' => (string) Str::uuid(), 'physical_stop_id' => $firstPhysical->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active']);
    $this->lastStop = $this->line->stops()->create(['uuid' => (string) Str::uuid(), 'physical_stop_id' => $lastPhysical->id, 'name' => 'Lomé', 'city' => 'Lomé', 'country' => 'TG', 'stop_order' => 2, 'km_from_origin' => 150, 'status' => 'active']);
    $this->bus = Bus::query()->create(['registration_number' => 'KORA-TEST', 'brand' => 'KORA', 'model' => 'Bus', 'seat_count' => 1, 'status' => 'active']);
    Seat::query()->create(['bus_id' => $this->bus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    Fare::query()->create(['line_id' => $this->line->id, 'origin_stop_id' => $this->firstStop->id, 'destination_stop_id' => $this->lastStop->id, 'base_amount' => 5000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);
});

function workflowSlot(object $test, ?string $busUuid): array
{
    return [
        'key' => (string) Str::uuid(), 'direction' => 'outbound', 'line_uuid' => $test->line->uuid,
        'timezone' => 'Africa/Porto-Novo', 'time' => '07:00', 'days' => [1, 2, 3, 4, 5, 6, 7], 'interval_weeks' => 1,
        'duration_minutes' => 180, 'preparation_minutes' => 0, 'bus_uuid' => $busUuid,
        'driver_uuid' => null, 'secondary_driver_uuid' => null, 'conductor_uuid' => null, 'mechanic_uuid' => null,
        'return_key' => null, 'return_day_offset' => 0, 'turnaround_minutes' => 30,
        'stops' => [
            ['stop_uuid' => $test->firstStop->uuid, 'arrival_minutes' => null, 'departure_minutes' => 0, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $test->lastStop->uuid, 'arrival_minutes' => 180, 'departure_minutes' => null, 'timezone' => 'Africa/Lome'],
        ],
    ];
}

function workflowPublishTemplate(object $test, array $slot): DepartureTemplateVersion
{
    $test->actingAs($test->actor)->post(route('programming.templates.store'), [
        'name' => 'Hebdo Cotonou ↔ Lomé', 'revision' => 0, 'ready' => true, 'slots' => [$slot],
    ])->assertRedirect();

    return DepartureTemplateVersion::query()->latest('id')->firstOrFail();
}

function workflowApply(object $test, DepartureTemplateVersion $version, string $name = 'Septembre'): DepartureProgramme
{
    $test->actingAs($test->actor)->post(route('programming.programmes.store'), [
        'name' => $name, 'version_uuid' => $version->uuid,
        'starts_on' => '2026-09-07', 'ends_on' => '2026-09-13',
        'horizon_days' => 30, 'exceptions' => [],
    ])->assertRedirect();

    return DepartureProgramme::query()->latest('id')->firstOrFail();
}

function workflowPublish(object $test, DepartureProgramme $programme): array
{
    $items = $programme->occurrences()->get()
        ->map(fn (PlannedDeparture $item): array => ['uuid' => $item->uuid, 'revision' => $item->revision])
        ->all();

    return $test->actingAs($test->actor)
        ->postJson(route('programming.actions'), ['operation' => 'publish', 'items' => $items])
        ->assertOk()->json();
}

it('publishes a full applied programme end-to-end without excluding any occurrence', function (): void {
    $version = workflowPublishTemplate($this, workflowSlot($this, $this->bus->uuid));
    $programme = workflowApply($this, $version);

    expect($programme->occurrences()->count())->toBe(7)
        ->and($programme->occurrences()->where('state', 'planned')->count())->toBe(7)
        ->and($programme->occurrences()->where('state', 'excluded')->count())->toBe(0);

    $result = workflowPublish($this, $programme);

    expect($result['processed'])->toBe(7)->and($result['errors'])->toBe([])
        ->and($programme->occurrences()->whereNull('departure_id')->count())->toBe(0)
        ->and($programme->occurrences()->where('state', 'excluded')->count())->toBe(0)
        ->and(Departure::query()->where('status', 'scheduled')->count())->toBe(7);
});

it('blocks but never excludes applied departures colliding with already published ones', function (): void {
    $version = workflowPublishTemplate($this, workflowSlot($this, $this->bus->uuid));
    $first = workflowApply($this, $version, 'Premier programme');
    workflowPublish($this, $first);

    // Deuxième modèle appliqué sur la même période avec le même bus.
    $otherVersion = workflowPublishTemplate($this, workflowSlot($this, $this->bus->uuid));
    $second = workflowApply($this, $otherVersion, 'Second programme');

    expect($second->occurrences()->count())->toBe(7)
        ->and($second->occurrences()->where('state', 'planned')->count())->toBe(7);

    $result = workflowPublish($this, $second);

    expect($result['processed'])->toBe(0)->and($result['errors'])->not->toBeEmpty()
        ->and($second->occurrences()->where('state', 'planned')->count())->toBe(7)
        ->and($second->occurrences()->where('state', 'excluded')->count())->toBe(0)
        ->and($second->occurrences()->whereNull('departure_id')->count())->toBe(7);

    $this->actingAs($this->actor)
        ->get(route('programming.index', ['date' => '2026-09-07', 'view' => 'week', 'programme' => $second->uuid]))
        ->assertSuccessful()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('counts.excluded', 0)
        ->where('counts.blocked', 7)
        ->where('counts.published', 0)
        ->etc());
});

it('blocks but never excludes applied departures colliding with a manual departure', function (): void {
    $this->actingAs($this->actor)->post(route('departures.store'), [
        'line_uuid' => $this->line->uuid, 'bus_uuid' => $this->bus->uuid,
        'departure_date' => '2026-09-08', 'departure_time' => '07:00',
    ])->assertRedirect();

    $version = workflowPublishTemplate($this, workflowSlot($this, $this->bus->uuid));
    $programme = workflowApply($this, $version);

    $result = workflowPublish($this, $programme);

    expect($programme->occurrences()->where('state', 'excluded')->count())->toBe(0)
        ->and($result['processed'])->toBe(6)
        ->and(array_keys($result['errors']))->toHaveCount(1)
        ->and($programme->occurrences()->whereNull('departure_id')->count())->toBe(1);
});

it('distinguishes manual resource assignments from schedule exceptions', function (): void {
    // Créneau sans bus : l'affectation manuelle est du remplissage, pas une exception d'horaire.
    $version = workflowPublishTemplate($this, workflowSlot($this, null));
    $programme = workflowApply($this, $version);
    [$first, $second] = $programme->occurrences()->orderBy('service_date')->limit(2)->get()->all();

    $this->actingAs($this->actor)->postJson(route('programming.actions'), [
        'operation' => 'assign',
        'items' => [['uuid' => $first->uuid, 'revision' => $first->revision]],
        'changes' => ['bus_uuid' => $this->bus->uuid],
    ])->assertOk();

    $this->actingAs($this->actor)->postJson(route('programming.actions'), [
        'operation' => 'change',
        'items' => [['uuid' => $second->uuid, 'revision' => $second->revision]],
        'changes' => ['time' => '09:30'],
    ])->assertOk();

    $assigned = $this->actingAs($this->actor)
        ->getJson(route('programming.items.show', $first->uuid))->assertOk()->json();
    expect($assigned['isException'])->toBeTrue()
        ->and($assigned['exceptionKind'])->toBe('resources')
        ->and($assigned['history'][0]['operation'])->toBe('assign');

    $changed = $this->actingAs($this->actor)
        ->getJson(route('programming.items.show', $second->uuid))->assertOk()->json();
    expect($changed['isException'])->toBeTrue()
        ->and($changed['exceptionKind'])->toBe('schedule')
        ->and($changed['history'][0]['operation'])->toBe('change');

    // L'affectation de ressources débloque la publication sans passer par une exclusion.
    $result = $this->actingAs($this->actor)->postJson(route('programming.actions'), [
        'operation' => 'publish',
        'items' => [['uuid' => $first->uuid, 'revision' => $first->fresh()->revision]],
    ])->assertOk()->json();

    expect($result['processed'])->toBe(1)->and($result['errors'])->toBe([])
        ->and($first->fresh()->state)->toBe('planned')
        ->and($first->fresh()->departure->getRawOriginal('status'))->toBe('scheduled');
});

it('keeps programme calendar exceptions flagged as schedule exceptions', function (): void {
    $slot = array_merge(workflowSlot($this, $this->bus->uuid), ['days' => [1, 2, 3, 4, 5]]);
    $version = workflowPublishTemplate($this, $slot);

    $this->actingAs($this->actor)->post(route('programming.programmes.store'), [
        'name' => 'Avec ajout', 'version_uuid' => $version->uuid,
        'starts_on' => '2026-09-07', 'ends_on' => '2026-09-13',
        'horizon_days' => 30,
        'exceptions' => [['slot_key' => $slot['key'], 'date' => '2026-09-13', 'action' => 'add']],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $added = PlannedDeparture::query()->whereDate('service_date', '2026-09-13')->sole();
    $item = $this->actingAs($this->actor)
        ->getJson(route('programming.items.show', $added->uuid))->assertOk()->json();

    expect($item['isException'])->toBeTrue()
        ->and($item['exceptionKind'])->toBe('schedule')
        ->and($item['state'])->not->toBe('excluded');
});
