<?php

use App\Models\User;
use App\Modules\BusNetwork\Data\LineStatus;
use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\Parcels\Models\ParcelHub;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function depositLocationFixtures(): array
{
    [$line, $originStop, $destinationStop] = lineWithStops();
    $agency = Agency::factory()->create(['country' => 'BJ']);
    $ops = User::factory()->create()->assignRole('operations_director');
    $passenger = User::factory()->create()->assignRole('passenger');

    return compact('line', 'originStop', 'destinationStop', 'agency', 'ops', 'passenger');
}

test('internal agency can be configured as a deposit location', function (): void {
    ['originStop' => $stop, 'agency' => $agency, 'ops' => $ops] = depositLocationFixtures();

    $response = $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'agency_uuid' => $agency->uuid,
        'stop_uuid' => $stop->uuid,
        'enabled' => true,
    ])->assertSuccessful()->assertJsonPath('data.kind', ParcelHub::KIND_AGENCY);

    expect($response->json('data'))->not->toHaveKey('id');
    $this->assertDatabaseHas('parcel_hubs', ['agency_id' => $agency->id, 'stop_id' => $stop->id, 'kind' => 'agency', 'kora_point_id' => null]);
});

test('partner point can be configured as a deposit location without duplicating its agency', function (): void {
    ['originStop' => $stop, 'ops' => $ops] = depositLocationFixtures();
    $partnerAgency = Agency::factory()->create(['kind' => 'partner', 'country' => 'BJ']);
    $point = KoraPoint::factory()->create(['agency_id' => $partnerAgency->id]);

    $response = $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'kora_point_uuid' => $point->uuid,
        'stop_uuid' => $stop->uuid,
        'enabled' => true,
    ])->assertSuccessful()
        ->assertJsonPath('data.kind', ParcelHub::KIND_PARTNER_POINT)
        ->assertJsonPath('data.kora_point_uuid', $point->uuid);

    expect($response->json('data'))->not->toHaveKey('id');
    $this->assertDatabaseHas('parcel_hubs', ['kora_point_id' => $point->id, 'agency_id' => $partnerAgency->id, 'kind' => 'partner_point']);
    $this->assertDatabaseHas('agencies', ['id' => $partnerAgency->id, 'kind' => 'partner']);
});

test('internal agency must be active before it can be configured as a deposit location', function (): void {
    ['originStop' => $stop, 'ops' => $ops] = depositLocationFixtures();
    $agency = Agency::factory()->create(['kind' => 'internal', 'country' => 'BJ', 'status' => AgencyStatus::Suspended]);

    $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'agency_uuid' => $agency->uuid,
        'stop_uuid' => $stop->uuid,
        'enabled' => true,
    ])->assertUnprocessable();

    $this->assertDatabaseMissing('parcel_hubs', ['agency_id' => $agency->id, 'stop_id' => $stop->id]);
});

test('deposit stop must be active before it can host a deposit location', function (): void {
    ['originStop' => $stop, 'agency' => $agency, 'ops' => $ops] = depositLocationFixtures();
    $stop->update(['status' => StopStatus::Draft]);

    $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'agency_uuid' => $agency->uuid,
        'stop_uuid' => $stop->uuid,
        'enabled' => true,
    ])->assertUnprocessable();

    $this->assertDatabaseMissing('parcel_hubs', ['agency_id' => $agency->id, 'stop_id' => $stop->id]);
});

test('deposit stop must remain attached to an active line', function (): void {
    ['line' => $line, 'originStop' => $stop, 'agency' => $agency, 'ops' => $ops] = depositLocationFixtures();
    $line->update(['status' => LineStatus::Draft]);

    $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'agency_uuid' => $agency->uuid,
        'stop_uuid' => $stop->uuid,
        'enabled' => true,
    ])->assertUnprocessable();

    $this->assertDatabaseMissing('parcel_hubs', ['agency_id' => $agency->id, 'stop_id' => $stop->id]);
});

test('partner point bound to an internal agency is rejected', function (): void {
    ['originStop' => $stop, 'ops' => $ops] = depositLocationFixtures();
    $internal = Agency::factory()->create(['kind' => 'internal', 'country' => 'BJ']);
    $point = KoraPoint::factory()->create(['agency_id' => $internal->id]);

    $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'kora_point_uuid' => $point->uuid,
        'stop_uuid' => $stop->uuid,
        'enabled' => true,
    ])->assertUnprocessable();
});

test('an internal agency cannot be attached to two deposit locations', function (): void {
    ['originStop' => $origin, 'destinationStop' => $destination, 'agency' => $agency, 'ops' => $ops] = depositLocationFixtures();

    $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'agency_uuid' => $agency->uuid, 'stop_uuid' => $origin->uuid, 'enabled' => true,
    ])->assertSuccessful();

    $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'agency_uuid' => $agency->uuid, 'stop_uuid' => $destination->uuid, 'enabled' => true,
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Internal agency is already attached to another deposit location.');

    expect(ParcelHub::query()->where('agency_id', $agency->id)->count())->toBe(1);
});

test('a partner point cannot be attached to two deposit locations', function (): void {
    ['originStop' => $origin, 'destinationStop' => $destination, 'ops' => $ops] = depositLocationFixtures();
    $partnerAgency = Agency::factory()->create(['kind' => 'partner', 'country' => 'BJ']);
    $point = KoraPoint::factory()->create(['agency_id' => $partnerAgency->id]);

    $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'kora_point_uuid' => $point->uuid, 'stop_uuid' => $origin->uuid, 'enabled' => true,
    ])->assertSuccessful();

    $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'kora_point_uuid' => $point->uuid, 'stop_uuid' => $destination->uuid, 'enabled' => true,
    ])->assertUnprocessable();

    expect(ParcelHub::query()->where('kora_point_id', $point->id)->count())->toBe(1);
});

test('deposit location configuration requires the configure permission', function (): void {
    ['originStop' => $stop, 'agency' => $agency, 'passenger' => $passenger] = depositLocationFixtures();

    $this->actingAs($passenger, 'web')->putJson('/parcels/data/hubs', [
        'agency_uuid' => $agency->uuid, 'stop_uuid' => $stop->uuid, 'enabled' => true,
    ])->assertForbidden();
});

test('parcel configuration page renders for an operator with network permission', function (): void {
    ['ops' => $ops] = depositLocationFixtures();

    $this->actingAs($ops, 'web')
        ->get('/sale/parcels')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sale-parcels-index'));
});

test('configuration exposes eligible deposit options without exposing numeric identifiers', function (): void {
    ['originStop' => $stop, 'agency' => $agency, 'ops' => $ops] = depositLocationFixtures();
    $partnerAgency = Agency::factory()->create(['kind' => 'partner', 'country' => 'BJ']);
    $point = KoraPoint::factory()->create(['agency_id' => $partnerAgency->id, 'status' => 'active']);

    $payload = $this->actingAs($ops, 'web')
        ->getJson('/parcels/data/configuration')
        ->assertSuccessful()
        ->assertJsonPath('data.permissions.canConfigure', true)
        ->json('data');

    $internalAgency = collect($payload['options']['internal_agencies'])->firstWhere('uuid', $agency->uuid);
    $partnerPoint = collect($payload['options']['partner_points'])->firstWhere('uuid', $point->uuid);
    $depositStop = collect($payload['options']['stops'])->firstWhere('uuid', $stop->uuid);

    expect($internalAgency)->not->toBeNull()
        ->and($internalAgency)->not->toHaveKey('id')
        ->and($partnerPoint)->not->toBeNull()
        ->and($partnerPoint['agency_uuid'])->toBe($partnerAgency->uuid)
        ->and($depositStop)->not->toBeNull()
        ->and($depositStop['line_code'])->toBe($stop->line->code)
        ->and(Str::isUuid($depositStop['uuid']))->toBeTrue();
});

test('archiving a deposit location keeps history and removes it from active configuration', function (): void {
    ['originStop' => $stop, 'agency' => $agency, 'ops' => $ops] = depositLocationFixtures();

    $uuid = $this->actingAs($ops, 'web')->putJson('/parcels/data/hubs', [
        'agency_uuid' => $agency->uuid, 'stop_uuid' => $stop->uuid, 'enabled' => true,
    ])->assertSuccessful()->json('data.uuid');

    $this->actingAs($ops, 'web')->postJson("/parcels/data/hubs/{$uuid}/archive")
        ->assertSuccessful()->assertJsonPath('data.enabled', false);

    $hub = ParcelHub::query()->where('uuid', $uuid)->firstOrFail();
    expect($hub->archived_at)->not->toBeNull();
    expect($hub->isActive())->toBeFalse();

    $this->actingAs($ops, 'web')->postJson("/parcels/data/hubs/{$uuid}/archive")->assertConflict();

    $hubs = $this->actingAs($ops, 'web')->getJson('/parcels/data/configuration')->assertSuccessful()->json('data.hubs');
    $entry = collect($hubs)->firstWhere('uuid', $uuid);
    expect($entry['archived_at'])->not->toBeNull();
    expect(Str::isUuid($entry['uuid']))->toBeTrue();
});
