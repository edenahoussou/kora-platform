<?php

namespace App\Modules\Departures\Services;

use App\Models\User;
use App\Modules\Boarding\Data\SeatReleaseEventStatus;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Models\Departure;
use Illuminate\Support\Facades\Gate;

/**
 * Construit le payload Inertia complet de la fiche départ : identité,
 * occupation segment-aware, passagers du manifeste (regroupés par arrêt
 * de montée côté front), résumé embarquement et chronologie réelle.
 * Le contrôleur reste une coquille : authorize → service → inertia.
 */
class DepartureDetailService
{
    public function __construct(
        private readonly DepartureOccupancyService $occupancyService,
        private readonly ManifestPassengerSerializer $manifestSerializer,
    ) {}

    /** @return array<string, mixed> */
    public function payload(Departure $departure, User $user): array
    {
        $departure->load([
            'line.stops' => fn ($q) => $q->where('status', 'active')->orderBy('stop_order')->with('physicalStop:id,uuid,name,city'),
            'bus:id,uuid,registration_number,seat_count',
            'bus.seats' => fn ($q) => $q->orderByRaw('LENGTH(code), code'),
            'driver:id,uuid,name',
            'conductor:id,uuid,name',
            'creator:id,name',
            'plannedOccurrence.programme:id,uuid',
        ]);

        $canViewTickets = $user->can('tickets.view');

        $segments = $departure->reservationSegments()
            ->with([
                'reservationPassenger',
                'reservation.payments' => fn ($q) => $q->orderByDesc('created_at'),
                'reservation.passengers:id,reservation_id',
                'seat:id,uuid,code',
                'originStop:id,uuid,name',
                'destinationStop:id,uuid,name',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $ticketsByPassengerId = $this->manifestSerializer->ticketsByPassengerId($segments);

        return [
            'departure' => $this->serializeDeparture($departure),
            'passengers' => $segments->map(fn (ReservationSegment $segment): array => $this->manifestSerializer->row($segment, $ticketsByPassengerId, $canViewTickets, $departure->status))->values(),
            'boardingSummary' => $this->manifestSerializer->summary($segments, $departure->status),
            'timeline' => $this->buildTimeline($departure),
            'permissions' => [
                'canUpdate' => Gate::allows('update', $departure),
                'canCancel' => Gate::allows('delete', $departure),
                'canManageReleases' => $user->can('departures.manage'),
                'canReverseBoarding' => $user->can('boarding.reverse'),
                'canViewTickets' => $canViewTickets,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function serializeDeparture(Departure $departure): array
    {
        $stops = [];
        foreach ($departure->line->stops ?? [] as $stop) {
            $stops[] = [
                'uuid' => $stop->uuid,
                'name' => $stop->name,
                'stopOrder' => $stop->stop_order,
                'city' => $stop->city,
                'physicalStopName' => $stop->physicalStop?->name,
            ];
        }

        $seatCount = $departure->bus->seat_count ?? 0;
        $occupancy = $this->occupancyService->occupancy($departure, $seatCount);

        $occupiedSeatIds = $departure->reservationSegments()
            ->whereIn('status', ReservationSegmentStatus::occupyingValues())
            ->distinct()
            ->pluck('seat_id')
            ->all();

        $boardedSeatIds = $departure->reservationSegments()
            ->where('status', ReservationSegmentStatus::Boarded->value)
            ->distinct()
            ->pluck('seat_id')
            ->all();

        $seats = [];
        foreach ($departure->bus->seats ?? [] as $seat) {
            $seats[] = [
                'uuid' => $seat->uuid,
                'code' => $seat->code,
                'type' => (string) $seat->getRawOriginal('type'),
                'sellable' => (bool) $seat->sellable,
                'status' => (string) $seat->getRawOriginal('status'),
                'occupied' => in_array($seat->id, $occupiedSeatIds, true),
                'boarded' => in_array($seat->id, $boardedSeatIds, true),
            ];
        }

        $originStop = $departure->line?->stops?->first();
        $destinationStop = $departure->line?->stops?->last();

        return [
            'uuid' => $departure->uuid,
            'lineUuid' => $departure->line?->uuid,
            'lineCode' => $departure->line->code ?? '—',
            'lineName' => $departure->line->name ?? '—',
            'lineEstimatedDuration' => $departure->line->estimated_duration_minutes,
            'originCity' => $originStop?->physicalStop->city ?? $originStop?->city,
            'destinationCity' => $destinationStop?->physicalStop->city ?? $destinationStop?->city,
            'originPoint' => $originStop?->physicalStop->name ?? $originStop?->name,
            'stops' => $stops,
            'busUuid' => $departure->bus?->uuid,
            'busRegistration' => $departure->bus->registration_number ?? 'À affecter',
            'busSeatCount' => $seatCount,
            'seats' => $seats,
            'departureDate' => $departure->departure_date?->format('Y-m-d'),
            'departureTime' => substr((string) $departure->departure_time, 0, 5),
            'delayMinutes' => (int) $departure->delay_minutes,
            'delayReason' => $departure->delay_reason,
            'delayRecordedAt' => $departure->delay_recorded_at?->toIso8601String(),
            'estimatedArrivalAt' => $departure->estimated_arrival_at?->toIso8601String(),
            'driverUuid' => $departure->driver?->uuid,
            'driverName' => $departure->driver?->name,
            'driverAvatar' => $departure->driver?->getFirstMediaUrl('avatar', 'thumb') ?: null,
            'conductorUuid' => $departure->conductor?->uuid,
            'conductorName' => $departure->conductor?->name,
            'conductorAvatar' => $departure->conductor?->getFirstMediaUrl('avatar', 'thumb') ?: null,
            'creatorName' => $departure->creator?->name,
            'fillRate' => $occupancy['peakFillRate'],
            'averageFillRate' => $occupancy['averageFillRate'],
            'peakLegLabel' => $occupancy['peakLegLabel'],
            'peakLoad' => $occupancy['peakLoad'],
            'reservationsCount' => $occupancy['passengers'],
            'status' => (string) $departure->getRawOriginal('status'),
            'createdAt' => $departure->created_at?->toIso8601String(),
            'updatedAt' => $departure->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Chronologie réelle : jalons de statut, scans embarquement,
     * libérations de siège et retard — tout horodaté.
     *
     * @return array<int, array{label: string, date: string|null}>
     */
    private function buildTimeline(Departure $departure): array
    {
        $events = [];

        if ($departure->created_at !== null) {
            $events[] = ['label' => 'Départ programmé', 'date' => $departure->created_at->toIso8601String()];
        }

        if ($departure->boarding_started_at !== null) {
            $events[] = ['label' => 'Embarquement ouvert', 'date' => $departure->boarding_started_at->toIso8601String()];
        }

        $boardedCount = BoardingEvent::query()->where('departure_id', $departure->id)->count();
        if ($boardedCount > 0) {
            $lastScan = BoardingEvent::query()->where('departure_id', $departure->id)->max('server_recorded_at');
            $events[] = [
                'label' => __(':count embarquement(s) validé(s)', ['count' => $boardedCount]),
                'date' => $lastScan !== null ? (string) $lastScan : null,
            ];
        }

        $releaseCount = SeatReleaseEvent::query()
            ->where('departure_id', $departure->id)
            ->where('status', SeatReleaseEventStatus::Accepted)
            ->count();
        if ($releaseCount > 0) {
            $lastRelease = SeatReleaseEvent::query()
                ->where('departure_id', $departure->id)
                ->where('status', SeatReleaseEventStatus::Accepted)
                ->max('server_recorded_at');
            $events[] = [
                'label' => __(':count siège(s) libéré(s)', ['count' => $releaseCount]),
                'date' => $lastRelease !== null ? (string) $lastRelease : null,
            ];
        }

        if ($departure->delay_recorded_at !== null) {
            $events[] = [
                'label' => __('Retard de :minutes min enregistré', ['minutes' => (int) $departure->delay_minutes]),
                'date' => $departure->delay_recorded_at->toIso8601String(),
            ];
        }

        if ($departure->departed_at !== null) {
            $events[] = ['label' => 'Départ effectif', 'date' => $departure->departed_at->toIso8601String()];
        }

        if ($departure->arrived_at !== null) {
            $events[] = ['label' => 'Arrivée déclarée', 'date' => $departure->arrived_at->toIso8601String()];
        }

        return $events;
    }
}
