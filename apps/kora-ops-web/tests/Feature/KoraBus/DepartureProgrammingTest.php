<?php

use App\Jobs\MaintainDepartureProgramme;
use App\Jobs\ProcessProgrammingSelection;
use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Models\DepartureProgramme;
use App\Modules\Departures\Models\DepartureTemplate;
use App\Modules\Departures\Services\PlanningReadService;
use App\Modules\Departures\Services\PlanningService;
use App\Modules\Departures\Services\PublicationService;
use App\Modules\Departures\Services\RecurrenceService;
use App\Modules\Departures\Services\TemplateService;
use App\Modules\DynamicPricing\Models\Fare;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 09:00:00'));
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->actor = User::factory()->create();
    $this->actor->assignRole('operations_director');
    $this->line = Line::query()->create(['code' => 'COT-LOM', 'name' => 'Cotonou → Lomé', 'type' => 'internationale', 'status' => 'active', 'estimated_duration_minutes' => 180]);
    $firstPhysical = PhysicalStop::query()->create(['uuid' => (string) Str::uuid(), 'name' => 'Cotonou DP', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $lastPhysical = PhysicalStop::query()->create(['uuid' => (string) Str::uuid(), 'name' => 'Lomé DP', 'city' => 'Lomé', 'country' => 'TG', 'status' => 'active']);
    $first = $this->line->stops()->create(['uuid' => (string) Str::uuid(), 'physical_stop_id' => $firstPhysical->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active']);
    $last = $this->line->stops()->create(['uuid' => (string) Str::uuid(), 'physical_stop_id' => $lastPhysical->id, 'name' => 'Lomé', 'city' => 'Lomé', 'country' => 'TG', 'stop_order' => 2, 'km_from_origin' => 150, 'status' => 'active']);
    $this->bus = Bus::query()->create(['registration_number' => 'KORA-TEST', 'brand' => 'KORA', 'model' => 'Bus', 'seat_count' => 1, 'status' => 'active']);
    Seat::query()->create(['bus_id' => $this->bus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    Fare::query()->create(['line_id' => $this->line->id, 'origin_stop_id' => $first->id, 'destination_stop_id' => $last->id, 'base_amount' => 5000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);
    $this->slot = [
        'key' => (string) Str::uuid(), 'direction' => 'outbound', 'line_uuid' => $this->line->uuid,
        'timezone' => 'Africa/Porto-Novo', 'time' => '07:00', 'days' => [1, 2, 3, 4, 5], 'interval_weeks' => 1,
        'duration_minutes' => 180, 'preparation_minutes' => 0, 'bus_uuid' => null, 'driver_uuid' => null, 'secondary_driver_uuid' => null, 'conductor_uuid' => null, 'mechanic_uuid' => null,
        'return_key' => null, 'return_day_offset' => 0, 'turnaround_minutes' => 30,
        'stops' => [['stop_uuid' => $first->uuid, 'arrival_minutes' => null, 'departure_minutes' => 0, 'timezone' => 'Africa/Porto-Novo'], ['stop_uuid' => $last->uuid, 'arrival_minutes' => 180, 'departure_minutes' => null, 'timezone' => 'Africa/Lome']],
    ];
});

function programmingProgramme(object $test, array $overrides = []): DepartureProgramme
{
    $template = app(TemplateService::class)->save(['name' => 'Semaine Cotonou ↔ Lomé', 'revision' => 0, 'ready' => true, 'slots' => [$test->slot]], $test->actor);

    return app(PlanningService::class)->programme(array_replace(['name' => 'Septembre', 'version_uuid' => $template->versions()->first()->uuid, 'starts_on' => '2026-09-07', 'ends_on' => '2026-09-20', 'horizon_days' => 30, 'exceptions' => []], $overrides), $test->actor);
}

it('prepares weekdays without creating sellable departures and safely retries generation', function (): void {
    $programme = programmingProgramme($this);
    $service = app(PlanningService::class);
    $service->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    $service->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    expect($programme->occurrences()->count())->toBe(10)->and(Departure::count())->toBe(0);
});

it('keeps exclusions and adds exceptional circulation dates', function (): void {
    $programme = programmingProgramme($this, ['exceptions' => [['slot_key' => $this->slot['key'], 'date' => '2026-09-07', 'action' => 'exclude'], ['slot_key' => $this->slot['key'], 'date' => '2026-09-12', 'action' => 'add']]]);
    app(PlanningService::class)->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    expect($programme->occurrences()->count())->toBe(11)->and($programme->occurrences()->where('state', 'excluded')->count())->toBe(1);
});

it('requires a bus for publication then publishes once with local timezone preserved', function (): void {
    $programme = programmingProgramme($this);
    $planning = app(PlanningService::class);
    $planning->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    $item = $programme->occurrences()->first();
    expect(app(PublicationService::class)->assess($item)['state'])->toBe('unassigned');
    $result = $planning->mutate([['uuid' => $item->uuid, 'revision' => 1]], 'publish', [], $this->actor);
    expect($result['processed'])->toBe(0)->and($result['errors'])->not->toBeEmpty();
    $planning->mutate([['uuid' => $item->uuid, 'revision' => 1]], 'assign', ['bus_uuid' => $this->bus->uuid], $this->actor);
    $item->refresh();
    $planning->mutate([['uuid' => $item->uuid, 'revision' => $item->revision]], 'publish', [], $this->actor);
    $planning->mutate([['uuid' => $item->uuid, 'revision' => $item->revision]], 'publish', [], $this->actor);
    expect(Departure::count())->toBe(1)->and(Departure::first()->scheduledDepartureAt()->utc()->format('H:i'))->toBe('06:00');
});

it('rejects overlapping resource assignments', function (): void {
    $this->slot['bus_uuid'] = $this->bus->uuid;
    $programme = programmingProgramme($this);
    app(PlanningService::class)->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    $other = programmingProgramme($this);
    app(PlanningService::class)->generate($other, $other->starts_on, $other->ends_on, $this->actor);
    expect(app(PublicationService::class)->assess($programme->occurrences()->first())['state'])->toBe('blocked');
});

it('keeps programmes pinned when templates gain a new version', function (): void {
    $programme = programmingProgramme($this);
    $template = $programme->version->template;
    $old = $programme->version_id;
    $slot = array_replace($this->slot, ['time' => '08:00']);
    app(TemplateService::class)->save(['name' => $template->name, 'revision' => $template->revision, 'ready' => true, 'slots' => [$slot]], $this->actor, $template);
    expect($programme->refresh()->version_id)->toBe($old)->and($template->versions()->count())->toBe(2);
});

it('rejects stale generation previews without creating departures', function (): void {
    $programme = programmingProgramme($this);
    $preview = app(PlanningService::class)->preview($programme, $programme->starts_on, $programme->ends_on);
    $programme->increment('revision');
    $this->actingAs($this->actor)->postJson(route('programming.programmes.actions', $programme->uuid), ['operation' => 'generate', 'from' => $programme->starts_on->toDateString(), 'until' => $programme->ends_on->toDateString(), 'token' => $preview['token']])->assertConflict();
    expect($programme->occurrences()->count())->toBe(0);
});

it('denies programming to sales agents', function (): void {
    $user = User::factory()->create();
    $user->assignRole('agency_agent');
    $this->actingAs($user)->get(route('programming.index'))->assertForbidden();
    $this->actingAs($user)->postJson(route('programming.templates.store'), [])->assertForbidden();
});

it('validates stop chronology and saves incomplete drafts', function (): void {
    $this->actingAs($this->actor)->post(route('programming.templates.store'), ['name' => 'Brouillon', 'revision' => 0, 'ready' => false, 'slots' => []])->assertRedirect();
    expect(DepartureTemplate::first()->status)->toBe('draft');
    $this->slot['stops'][1]['arrival_minutes'] = 10;
    $this->actingAs($this->actor)->postJson(route('programming.templates.store'), ['name' => 'Incorrect', 'revision' => 0, 'ready' => true, 'slots' => [$this->slot]])->assertUnprocessable();
});

it('renders planning with manual departures and only public identifiers', function (): void {
    $programme = programmingProgramme($this);
    app(PlanningService::class)->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    $manual = Departure::query()->create(['line_id' => $this->line->id, 'bus_id' => $this->bus->id, 'departure_date' => '2026-09-07', 'departure_time' => '13:00', 'status' => 'scheduled']);
    $this->actingAs($this->actor)->get(route('programming.index', ['date' => '2026-09-07', 'view' => 'list']))->assertSuccessful()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('programming/index')->has('items', 2)->where('items.1.uuid', $manual->uuid)->where('items.1.manual', true)
        ->missing('items.0.id')->missing('templates.0.id')->missing('references.buses.0.id'));
});

it('prevents manual departure creation from stealing a programmed bus', function (): void {
    $this->slot['bus_uuid'] = $this->bus->uuid;
    $programme = programmingProgramme($this);
    app(PlanningService::class)->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    $this->actingAs($this->actor)->postJson(route('departures.store'), ['line_uuid' => $this->line->uuid, 'bus_uuid' => $this->bus->uuid, 'departure_date' => '2026-09-07', 'departure_time' => '08:00'])->assertUnprocessable();
    expect(Departure::count())->toBe(0);
});

it('preserves paid departures and manual exceptions when applying a version', function (): void {
    $this->slot['bus_uuid'] = $this->bus->uuid;
    $programme = programmingProgramme($this);
    $planning = app(PlanningService::class);
    $planning->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    $first = $programme->occurrences()->orderBy('service_date')->first();
    $planning->mutate([['uuid' => $first->uuid, 'revision' => 1]], 'publish', [], $this->actor);
    $reservation = Reservation::create(['status' => 'cancelled', 'total_amount' => 5000, 'currency' => 'XOF', 'channel' => 'online']);
    ReservationSegment::create([
        'reservation_id' => $reservation->id,
        'leg_order' => 1,
        'departure_id' => $first->refresh()->departure->id,
        'seat_id' => Seat::query()->where('bus_id', $first->refresh()->departure->bus_id)->first()->id,
        'origin_stop_id' => Stop::query()->where('line_id', $first->refresh()->departure->line_id)->orderBy('stop_order')->first()->id,
        'destination_stop_id' => Stop::query()->where('line_id', $first->refresh()->departure->line_id)->orderBy('stop_order', 'desc')->first()->id,
        'origin_stop_order' => 1,
        'destination_stop_order' => 2,
        'status' => 'cancelled',
    ]);
    $reservation->payments()->create(['provider' => 'sandbox', 'provider_reference' => 'PAID', 'idempotency_key' => (string) Str::uuid(), 'amount' => 5000, 'currency' => 'XOF', 'status' => 'confirmed']);
    $second = $programme->occurrences()->whereDate('service_date', '2026-09-08')->first();
    $planning->mutate([['uuid' => $second->uuid, 'revision' => 1]], 'change', ['time' => '06:00'], $this->actor);
    $template = $programme->version->template;
    app(TemplateService::class)->save(['name' => $template->name, 'revision' => $template->revision, 'ready' => true, 'slots' => [array_replace($this->slot, ['time' => '09:00'])]], $this->actor, $template);
    $version = $template->versions()->orderByDesc('number')->first();
    $preview = $planning->revise($programme, $version, $programme->starts_on, false, null, $this->actor);
    expect(collect($preview['rows'])->pluck('kind')->countBy()->all())->toBe(['protected' => 1, 'exception' => 1, 'update' => 8]);
    $planning->revise($programme, $version, $programme->starts_on, true, $preview['token'], $this->actor);
    expect($first->refresh()->snapshot['time'])->toBe('07:00')->and($second->refresh()->snapshot['time'])->toBe('06:00')
        ->and($programme->occurrences()->whereDate('service_date', '2026-09-09')->first()->snapshot['time'])->toBe('09:00');
    $result = $planning->mutate([['uuid' => $first->uuid, 'revision' => $first->revision]], 'exclude', [], $this->actor);
    expect($result['processed'])->toBe(0)->and($first->departure->refresh()->getRawOriginal('status'))->toBe('scheduled');
});

it('rejects a stale version preview atomically', function (): void {
    $programme = programmingProgramme($this);
    $planning = app(PlanningService::class);
    $planning->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    $template = $programme->version->template;
    app(TemplateService::class)->save(['name' => $template->name, 'revision' => $template->revision, 'ready' => true, 'slots' => [array_replace($this->slot, ['time' => '09:00'])]], $this->actor, $template);
    $version = $template->versions()->orderByDesc('number')->first();
    $this->actingAs($this->actor)->postJson(route('programming.programmes.actions', $programme->uuid), ['operation' => 'revision', 'from' => '2026-09-07', 'version_uuid' => $version->uuid, 'apply' => true, 'token' => 'stale'])->assertConflict();
    expect($programme->occurrences()->get()->pluck('snapshot.time')->unique()->all())->toBe(['07:00']);
});

it('publishes a linked rotation atomically and validates turnaround time', function (): void {
    $return = array_replace($this->slot, ['key' => (string) Str::uuid(), 'direction' => 'return', 'time' => '11:00', 'bus_uuid' => $this->bus->uuid]);
    $this->slot = array_replace($this->slot, ['return_key' => $return['key'], 'bus_uuid' => $this->bus->uuid]);
    $template = app(TemplateService::class)->save(['name' => 'Rotation', 'revision' => 0, 'ready' => true, 'slots' => [$this->slot, $return]], $this->actor);
    $programme = app(PlanningService::class)->programme(['name' => 'Rotation', 'version_uuid' => $template->versions()->first()->uuid, 'starts_on' => '2026-09-07', 'ends_on' => '2026-09-07', 'horizon_days' => 7, 'exceptions' => []], $this->actor);
    app(PlanningService::class)->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    $outbound = $programme->occurrences()->where('slot_key', $this->slot['key'])->first();
    $returnItem = $programme->occurrences()->where('slot_key', $return['key'])->first();
    $returnItem->update(['scheduled_at' => $outbound->arrival_at->addMinutes(15)]);
    $result = app(PlanningService::class)->mutate([['uuid' => $outbound->uuid, 'revision' => 1]], 'publish', [], $this->actor);
    expect($result['processed'])->toBe(0)->and(Departure::count())->toBe(0);
    $returnItem->update(['scheduled_at' => $outbound->arrival_at->addHour()]);
    $result = app(PlanningService::class)->mutate([['uuid' => $outbound->uuid, 'revision' => 1]], 'publish', [], $this->actor);
    expect($result['processed'])->toBe(2)->and(Departure::count())->toBe(2);
});

it('checks the exact automatic horizon and uses the approving manager', function (): void {
    $programme = programmingProgramme($this);
    $programme->update(['starts_on' => '2026-09-01']);
    $route = route('programming.programmes.actions', $programme->uuid);
    $payload = ['operation' => 'automatic', 'automatic' => true, 'revision' => $programme->revision, 'horizon_days' => 7];
    $this->actingAs($this->actor)->postJson($route, $payload)->assertConflict();
    $preview = app(PlanningService::class)->preview($programme, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-07'));
    $this->postJson($route, $payload + ['token' => $preview['token']])->assertSuccessful();
    expect($programme->refresh()->automatic)->toBeTrue()->and($programme->automatic_approved_by)->toBe($this->actor->id);
});

it('runs automatic generation before publication and safely resumes a run', function (): void {
    Queue::fake();
    $this->slot['bus_uuid'] = $this->bus->uuid;
    $programme = programmingProgramme($this, ['starts_on' => '2026-09-02', 'ends_on' => '2026-09-03']);
    $programme->update(['automatic' => true, 'automatic_approved_by' => $this->actor->id]);
    $this->artisan('bus:maintain-programmes')->assertSuccessful();
    $run = $programme->runs()->where('operation', 'automatic')->first();
    $job = new MaintainDepartureProgramme($run->id);
    $job->handle(app(PlanningService::class));
    expect(Departure::count())->toBe(0);
    $job->handle(app(PlanningService::class));
    expect(Departure::count())->toBe(0)->and($run->refresh()->result['phase'])->toBe('publish');
    $job->handle(app(PlanningService::class));
    $job->handle(app(PlanningService::class));
    $job->handle(app(PlanningService::class));
    expect($run->refresh()->status)->toBe('completed')->and(Departure::count())->toBe(2);
});

it('pauses queued automation when the approver loses permission', function (): void {
    Queue::fake();
    $programme = programmingProgramme($this);
    $programme->update(['automatic' => true, 'automatic_approved_by' => $this->actor->id]);
    $this->artisan('bus:maintain-programmes')->assertSuccessful();
    $run = $programme->runs()->where('operation', 'automatic')->first();
    $this->actor->syncRoles([]);
    (new MaintainDepartureProgramme($run->id))->handle(app(PlanningService::class));
    expect($run->refresh()->status)->toBe('paused')->and($programme->occurrences()->count())->toBe(0);
});

it('reports overlapping resources within an unsaved programme preview', function (): void {
    $this->slot['bus_uuid'] = $this->bus->uuid;
    $later = array_replace($this->slot, ['key' => (string) Str::uuid(), 'time' => '08:00']);
    $template = app(TemplateService::class)->save(['name' => 'Conflit', 'revision' => 0, 'ready' => true, 'slots' => [$this->slot, $later]], $this->actor);
    $this->actingAs($this->actor)->postJson(route('programming.programmes.preview'), ['name' => 'Aperçu', 'version_uuid' => $template->versions()->first()->uuid, 'starts_on' => '2026-09-07', 'ends_on' => '2026-09-07', 'horizon_days' => 7, 'exceptions' => []])->assertSuccessful()->assertJsonPath('rows.0.state', 'blocked')->assertJsonPath('rows.1.state', 'blocked');
    expect(DepartureProgramme::count())->toBe(0);
});

it('processes large selections in resumable batches without duplicate publication', function (): void {
    Queue::fake();
    $this->slot['bus_uuid'] = $this->bus->uuid;
    $programme = programmingProgramme($this, ['ends_on' => '2026-10-16']);
    $planning = app(PlanningService::class);
    $planning->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    $selection = $programme->occurrences()->get()->map(fn ($item): array => ['uuid' => $item->uuid, 'revision' => $item->revision])->all();
    $this->actingAs($this->actor)->postJson(route('programming.actions'), ['operation' => 'publish', 'items' => $selection])->assertAccepted()->assertJsonPath('queued', 30);
    $run = $programme->runs()->where('operation', 'publish')->first();
    $job = new ProcessProgrammingSelection($run->id);
    $job->handle($planning);
    expect($run->refresh()->processed)->toBe(25)->and($run->status)->toBe('queued');
    $job->handle($planning);
    $job->handle($planning);
    expect($run->refresh()->processed)->toBe(30)->and($run->status)->toBe('completed')->and(Departure::count())->toBe(30);
});

it('preserves absolute elapsed time across midnight and rejects nonexistent local departure time', function (): void {
    $this->slot['time'] = '23:00';
    $attributes = app(RecurrenceService::class)->attributes($this->slot, '2026-09-07');
    expect($attributes['arrival_at']->setTimezone('Africa/Lome')->format('Y-m-d H:i'))->toBe('2026-09-08 01:00');
    $this->slot['timezone'] = 'Europe/Paris';
    $this->slot['time'] = '02:30';
    expect(fn () => app(RecurrenceService::class)->attributes($this->slot, '2027-03-28'))->toThrow(ValidationException::class);
});

it('assesses a month without repeating reference queries for every occurrence', function (): void {
    $programme = programmingProgramme($this, ['ends_on' => '2026-10-01']);
    app(PlanningService::class)->generate($programme, $programme->starts_on, $programme->ends_on, $this->actor);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $workspace = app(PlanningReadService::class)->workspace(['date' => '2026-09-07', 'view' => 'month']);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(count($workspace['items']))->toBeGreaterThan(15)->and(count($queries))->toBeLessThan(75);
});
