<?php

use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\KoraPoints\Models\KoraPoint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function databaseHubPayload(int $agencyId, int $stopId, string $kind, ?int $pointId = null): array
{
    return [
        'uuid' => Str::uuid()->toString(), 'agency_id' => $agencyId, 'stop_id' => $stopId,
        'kind' => $kind, 'kora_point_id' => $pointId, 'enabled' => true,
        'created_at' => now(), 'updated_at' => now(),
    ];
}

function databaseConstraintFixtures(): array
{
    [$line, $originStop] = lineWithStops();
    $agency = Agency::factory()->create(['country' => 'BJ']);
    $partnerAgency = Agency::factory()->create(['kind' => 'partner', 'country' => 'BJ']);
    $point = KoraPoint::factory()->create(['agency_id' => $partnerAgency->id]);

    return compact('line', 'originStop', 'agency', 'partnerAgency', 'point');
}

test('kind check constraint rejects unknown deposit location kinds', function (): void {
    $fixture = databaseConstraintFixtures();

    expect(fn () => DB::table('parcel_hubs')->insert(databaseHubPayload($fixture['agency']->id, $fixture['originStop']->id, 'shelter')))
        ->toThrow(QueryException::class);
})->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'PostgreSQL only');

test('partner identity constraint requires a kora point for partner locations', function (): void {
    $fixture = databaseConstraintFixtures();

    expect(fn () => DB::table('parcel_hubs')->insert(databaseHubPayload($fixture['agency']->id, $fixture['originStop']->id, 'partner_point')))
        ->toThrow(QueryException::class);
})->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'PostgreSQL only');

test('agency identity constraint forbids a kora point on internal locations', function (): void {
    $fixture = databaseConstraintFixtures();

    expect(fn () => DB::table('parcel_hubs')->insert(databaseHubPayload($fixture['agency']->id, $fixture['originStop']->id, 'agency', $fixture['point']->id)))
        ->toThrow(QueryException::class);
})->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'PostgreSQL only');

test('a partner point can back only one deposit location', function (): void {
    $fixture = databaseConstraintFixtures();
    DB::table('parcel_hubs')->insert(databaseHubPayload($fixture['partnerAgency']->id, $fixture['originStop']->id, 'partner_point', $fixture['point']->id));

    expect(fn () => DB::table('parcel_hubs')->insert(databaseHubPayload($fixture['partnerAgency']->id, $fixture['originStop']->id + 1, 'partner_point', $fixture['point']->id)))
        ->toThrow(QueryException::class);
})->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'PostgreSQL only');
