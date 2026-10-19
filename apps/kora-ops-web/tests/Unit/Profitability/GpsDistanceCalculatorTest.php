<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Profitability\Services\GpsDistanceCalculator;
use App\Modules\Tracking\Models\TrackingPosition;
use App\Modules\Tracking\Models\TrackingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @return array{departure: Departure, session: TrackingSession}
 */
function gpsFixture(string $openedAt, string $closedAt): array
{
    $line = Line::query()->create([
        'uuid' => (string) Str::uuid(),
        'code' => 'L'.Str::random(5),
        'name' => 'Cotonou — Parakou',
        'type' => 'nationale',
        'distance_km' => 100,
        'status' => 'active',
    ]);
    $bus = Bus::query()->create([
        'uuid' => (string) Str::uuid(),
        'registration_number' => 'BJ-'.Str::random(6),
        'seat_count' => 50,
        'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-10-10',
        'departure_time' => '08:00',
        'status' => 'departed',
    ]);
    $session = TrackingSession::query()->create([
        'uuid' => (string) Str::uuid(),
        'departure_id' => $departure->id,
        'bus_id' => $bus->id,
        'terminal_p501_id' => TerminalP501::factory()->create()->id,
        'conductor_id' => User::factory()->create()->id,
        'status' => 'closed',
        'opened_at' => $openedAt,
        'closed_at' => $closedAt,
    ]);

    return ['departure' => $departure, 'session' => $session];
}

function gpsPosition(TrackingSession $session, int $sequence, string $recordedAt, float $longitude, ?float $accuracy = 10.0): void
{
    TrackingPosition::query()->create([
        'uuid' => (string) Str::uuid(),
        'tracking_session_id' => $session->id,
        'sequence' => $sequence,
        'status' => 'fix',
        'latitude' => 6.3654,
        'longitude' => $longitude,
        'accuracy_meters' => $accuracy,
        'source' => 'gps',
        'quality' => 'accurate',
        'recorded_at' => $recordedAt,
        'server_received_at' => $recordedAt,
    ]);
}

it('uses GPS distance when coverage and accuracy are sufficient', function (): void {
    ['departure' => $departure, 'session' => $session] = gpsFixture('2026-10-10 08:00:00', '2026-10-10 10:00:00');
    for ($index = 0; $index <= 12; $index++) {
        gpsPosition(
            $session,
            $index + 1,
            CarbonImmutable::parse('2026-10-10 08:00:00')->addMinutes($index * 10)->toDateTimeString(),
            2.4183 + $index * (0.1 / 6),
        );
    }

    $result = app(GpsDistanceCalculator::class)->forDepartures([$departure->id])[$departure->id];

    expect($result['source'])->toBe('gps')
        ->and($result['coveragePercent'])->toBe(100.0)
        ->and($result['distanceKm'])->toBeGreaterThan(21.0)
        ->and($result['distanceKm'])->toBeLessThan(23.5);
});

it('falls back to the planned distance when the GPS gap is too large', function (): void {
    ['departure' => $departure, 'session' => $session] = gpsFixture('2026-10-10 08:00:00', '2026-10-10 10:00:00');
    gpsPosition($session, 1, '2026-10-10 08:00:00', 2.4183);
    gpsPosition($session, 2, '2026-10-10 08:45:00', 2.5183);

    $result = app(GpsDistanceCalculator::class)->forDepartures([$departure->id])[$departure->id];

    expect($result['source'])->toBe('estimated')
        ->and($result['gpsDistanceKm'])->toBe(0.0)
        ->and($result['distanceKm'])->toBe(100.0)
        ->and($result['coveragePercent'])->toBe(0.0);
});

it('ignores degraded accuracy positions and falls back when there is no session', function (): void {
    ['departure' => $departure, 'session' => $session] = gpsFixture('2026-10-10 08:00:00', '2026-10-10 10:00:00');
    gpsPosition($session, 1, '2026-10-10 08:00:00', 2.4183, 500.0);
    gpsPosition($session, 2, '2026-10-10 09:00:00', 2.5183, 500.0);

    $withSession = app(GpsDistanceCalculator::class)->forDepartures([$departure->id])[$departure->id];

    $otherDeparture = Departure::query()->create([
        'line_id' => $departure->line_id,
        'bus_id' => $departure->bus_id,
        'departure_date' => '2026-10-11',
        'departure_time' => '08:00',
        'status' => 'scheduled',
    ]);
    $withoutSession = app(GpsDistanceCalculator::class)->forDepartures([$otherDeparture->id])[$otherDeparture->id];

    expect($withSession['source'])->toBe('estimated')
        ->and($withSession['hasSession'])->toBeTrue()
        ->and($withoutSession['source'])->toBe('estimated')
        ->and($withoutSession['hasSession'])->toBeFalse()
        ->and($withoutSession['distanceKm'])->toBe(100.0);
});
