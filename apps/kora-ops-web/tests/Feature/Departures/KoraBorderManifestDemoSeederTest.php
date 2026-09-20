<?php

use App\Models\User;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Actions\ComputeManifestVersionAction;
use App\Modules\Departures\Actions\GenerateBorderManifestPdfAction;
use App\Modules\Departures\Actions\ResolveManifestSegmentsAction;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Database\Seeders\KoraBorderManifestDemoSeeder;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Database\Seeders\KoraDemoUserSeeder;
use Mockery\MockInterface;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->seed(KoraDemoUserSeeder::class);
    $this->seed(KoraBorderManifestDemoSeeder::class);
});

function seededDeparture(string $registration): Departure
{
    return Departure::query()
        ->whereHas('bus', fn ($query) => $query->where('registration_number', $registration))
        ->firstOrFail();
}

/** @return array{X-Terminal-Serial: string, Authorization: string} */
function seededConductorHeaders(): array
{
    $user = User::query()->where('email', 'conductor.marc@kora.demo')->firstOrFail();

    return [
        'Authorization' => 'Bearer '.$user->createToken('device:field', ['*'], now()->addDays(7))->plainTextToken,
        'X-Terminal-Serial' => 'P501-FRONT-01',
    ];
}

/** Action réelle dont seul l'appel à Chromium est remplacé. */
function seededBorderManifestAction(): MockInterface
{
    return Mockery::mock(GenerateBorderManifestPdfAction::class, [
        app(ResolveManifestSegmentsAction::class),
        app(ComputeManifestVersionAction::class),
    ])->makePartial()->shouldAllowMockingProtectedMethods();
}

it('creates the active terminal and assigns the six departures to the conductor', function (): void {
    $conductor = User::query()->where('email', 'conductor.marc@kora.demo')->firstOrFail();

    expect(TerminalP501::query()->where('serial_number', 'P501-FRONT-01')->firstOrFail()->assigned_user_id)->toBe($conductor->id);

    foreach (['BJ-FRT-01', 'BJ-FRT-02', 'BJ-FRT-03', 'BJ-FRT-04', 'BJ-FRT-05', 'BJ-FRT-06'] as $registration) {
        expect(seededDeparture($registration)->conductor_id)->toBe($conductor->id);
    }
});

it('is re-runnable without duplicating passengers', function (): void {
    $before = ReservationSegment::query()->count();

    $this->seed(KoraBorderManifestDemoSeeder::class);

    expect(ReservationSegment::query()->count())->toBe($before);
});

it('flags departures A, B, D, E, F as international and C as national', function (): void {
    $headers = seededConductorHeaders();

    foreach (['BJ-FRT-01' => true, 'BJ-FRT-02' => true, 'BJ-FRT-03' => false, 'BJ-FRT-04' => true, 'BJ-FRT-05' => true, 'BJ-FRT-06' => true] as $registration => $international) {
        $this->withHeaders($headers)
            ->getJson('/api/v1/ops/departures/'.seededDeparture($registration)->uuid.'/manifest')
            ->assertOk()
            ->assertJsonPath('international', $international);
    }
});

it('A — prints the complete international manifest, long names included', function (): void {
    $action = seededBorderManifestAction();
    $action->shouldReceive('renderPdf')
        ->once()
        ->withArgs(function (string $html): bool {
            expect($html)->toContain('Bakary Salifou Ouedraogo')
                ->toContain('CI-SN-2026-0000123456789')
                ->toContain('NO-TYPE-42')
                ->not->toContain('XOF');

            return true;
        })
        ->andReturn('%PDF-A');
    $this->instance(GenerateBorderManifestPdfAction::class, $action);

    $this->withHeaders(seededConductorHeaders())
        ->get('/api/v1/ops/departures/'.seededDeparture('BJ-FRT-01')->uuid.'/manifest/pdf')
        ->assertOk();
});

it('B — refuses the incomplete manifest and names only the blocking passengers', function (): void {
    $action = seededBorderManifestAction();
    $action->shouldReceive('renderPdf')->never();
    $this->instance(GenerateBorderManifestPdfAction::class, $action);

    $response = $this->withHeaders(seededConductorHeaders())
        ->getJson('/api/v1/ops/departures/'.seededDeparture('BJ-FRT-02')->uuid.'/manifest/pdf')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'manifest_incomplete');

    $names = collect($response->json('error.passengers'))->pluck('name')->all();

    expect($names)->toHaveCount(3)
        ->toContain('Didier Sans-Nationalité', 'Eudoxie Sans-Document', 'Firmin Sans-Rien')
        ->not->toContain('Gilbert Annulé-Incomplet')
        ->not->toContain('Honorine Impayée-Incomplète');
});

it('D — refuses an international departure with no passenger', function (): void {
    $this->withHeaders(seededConductorHeaders())
        ->getJson('/api/v1/ops/departures/'.seededDeparture('BJ-FRT-04')->uuid.'/manifest/pdf')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'manifest_incomplete');
});

it('E — lists only the boarded passengers of the departure already on the road', function (): void {
    $action = seededBorderManifestAction();
    $action->shouldReceive('renderPdf')
        ->once()
        ->withArgs(function (string $html): bool {
            expect($html)->toContain('Mariam')->toContain('Wilfried')
                ->not->toContain('Xavier')   // absent : billet jamais utilisé
                ->not->toContain('Yvonne');  // annulée

            return true;
        })
        ->andReturn('%PDF-E');
    $this->instance(GenerateBorderManifestPdfAction::class, $action);

    $this->withHeaders(seededConductorHeaders())
        ->get('/api/v1/ops/departures/'.seededDeparture('BJ-FRT-05')->uuid.'/manifest/pdf')
        ->assertOk();
});

it('F — prints the 52-passenger manifest', function (): void {
    $action = seededBorderManifestAction();
    $action->shouldReceive('renderPdf')
        ->once()
        ->withArgs(function (string $html): bool {
            expect($html)->toContain('Bakayoko 52')->toContain('Agossou 1');

            return true;
        })
        ->andReturn('%PDF-F');
    $this->instance(GenerateBorderManifestPdfAction::class, $action);

    $this->withHeaders(seededConductorHeaders())
        ->get('/api/v1/ops/departures/'.seededDeparture('BJ-FRT-06')->uuid.'/manifest/pdf')
        ->assertOk();
});
