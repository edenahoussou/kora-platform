<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Tracking\Http\Resources\FleetTrackingResource;
use App\Modules\Tracking\Models\TrackingSession;
use App\Modules\Tracking\Services\FleetTrackingQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FleetTrackingPageController extends Controller
{
    public function index(Request $request, FleetTrackingQuery $fleetQuery): Response
    {
        $user = $request->user();

        if (! $user->can('tracking.view-network') && ! $user->can('tracking.view-agency')) {
            abort(403, 'Accès non autorisé au suivi de flotte.');
        }

        $canViewNetwork = $user->can('tracking.view-network');
        $agencyFilter = $canViewNetwork ? (string) $request->string('agency') : null;

        $sessions = $fleetQuery
            ->build($user, array_filter(['agency' => $agencyFilter]))
            ->get();

        $lastSeenAt = $fleetQuery->terminalLastSeenAt($sessions);

        $initialData = $sessions
            ->map(fn (TrackingSession $session): array => (new FleetTrackingResource(
                $session,
                $lastSeenAt[$session->terminal_p501_id] ?? null,
            ))->resolve($request))
            ->values();

        $lines = Line::query()
            ->orderBy('code')
            ->get(['uuid', 'code', 'name'])
            ->map(fn (Line $line): array => [
                'uuid' => $line->uuid,
                'code' => $line->code,
                'name' => $line->name,
            ]);

        $agencies = $canViewNetwork
            ? Agency::query()
                ->orderBy('name')
                ->get(['uuid', 'name', 'code'])
                ->map(fn (Agency $agency): array => [
                    'uuid' => $agency->uuid,
                    'name' => $agency->name,
                    'code' => $agency->code,
                ])
            : [];

        return Inertia::render('operations/fleet-tracking', [
            'sessions' => $initialData,
            'lines' => $lines,
            'agencies' => $agencies,
            'canViewNetwork' => $canViewNetwork,
            'userAgencyUuid' => $user->agency?->uuid,
            'filters' => [
                'q' => (string) $request->string('q'),
                'line' => (string) $request->string('line'),
                'status' => (string) $request->string('status'),
                'state' => (string) $request->string('state'),
                'agency' => (string) $request->string('agency'),
                'view' => (string) $request->string('view', 'hybrid'),
            ],
            'pollingIntervalMs' => 15000,
        ]);
    }
}
