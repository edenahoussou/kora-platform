<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Boarding\Actions\CloseNoShowsForDepartureAction;
use App\Modules\Boarding\Actions\ProcessIndividualNoShowAction;
use App\Modules\Boarding\Actions\ProcessSeatReleaseAction;
use App\Modules\Boarding\Actions\ReverseBoardingEventAction;
use App\Modules\Boarding\Data\BoardingEventStatus;
use App\Modules\Boarding\Data\SeatReleaseEventStatus;
use App\Modules\Boarding\Exceptions\SeatReleaseTicketUnknownException;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Actions\ChangeDepartureStatusAction;
use App\Modules\Departures\Actions\CreateDepartureAction;
use App\Modules\Departures\Actions\RecordDepartureDelayAction;
use App\Modules\Departures\Actions\UpdateDepartureAction;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Http\Requests\ChangeDepartureStatusRequest;
use App\Modules\Departures\Http\Requests\CloseDepartureNoShowsRequest;
use App\Modules\Departures\Http\Requests\RecordDepartureDelayRequest;
use App\Modules\Departures\Http\Requests\RecordEarlyExitRequest;
use App\Modules\Departures\Http\Requests\RecordPassengerNoShowRequest;
use App\Modules\Departures\Http\Requests\ReverseDepartureBoardingRequest;
use App\Modules\Departures\Http\Requests\StoreDepartureRequest;
use App\Modules\Departures\Http\Requests\UpdateDepartureRequest;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\DepartureDetailService;
use App\Modules\Departures\Services\DepartureOccupancyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DepartureController extends Controller
{
    public function index(Request $request, DepartureOccupancyService $occupancyService): Response
    {
        Gate::authorize('viewAny', Departure::class);

        $query = Departure::query()
            ->with([
                'line:id,uuid,code,name',
                'line.stops' => fn ($stops) => $stops->where('status', 'active')->orderBy('stop_order'),
                'line.stops.physicalStop:id,uuid,name,code,city',
                'bus:id,uuid,registration_number,seat_count',
                'driver:id,uuid,name',
                'conductor:id,uuid,name',
            ])
            ->orderBy('departure_time');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereHas('line', fn ($line) => $line->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('bus', fn ($bus) => $bus->whereRaw('LOWER(registration_number) LIKE ?', [$term]));
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, DepartureStatus::values(), true)) {
            $query->where('status', $status);
        }

        $lineUuid = $request->string('line_uuid')->trim()->value();
        if ($lineUuid !== '') {
            $line = Line::query()->where('uuid', $lineUuid)->first(['id']);
            if ($line !== null) {
                $query->where('line_id', $line->id);
            }
        }

        // Tableau d'exploitation du jour : sans date explicite, on affiche
        // les départs du jour courant dans le fuseau société — now() nu
        // reste en UTC car ApplyKoraSettings ne touche pas PHP.
        $date = $request->string('date')->trim()->value();
        if ($date === '') {
            $date = now(config('app.timezone'))->toDateString();
        }
        $query->whereDate('departure_date', $date);

        $city = $request->string('city')->trim()->value();
        if ($city !== '') {
            $query->whereHas('line.firstStop.physicalStop', fn ($stop) => $stop->where('city', $city));
        }

        $physicalStopUuid = $request->string('physical_stop')->trim()->value();
        if ($physicalStopUuid !== '') {
            $query->whereHas('line.firstStop.physicalStop', fn ($stop) => $stop->where('uuid', $physicalStopUuid));
        }

        $paginated = $query->paginate(15);

        $stats = $this->buildStats($date);

        return inertia('departures', [
            'departures' => $paginated->through(fn (Departure $departure): array => $this->serializeDeparture($departure, $occupancyService)),
            'statuses' => DepartureStatus::options(),
            'lines' => $this->lineOptions(),
            'cities' => $this->cityOptions(),
            'physicalStops' => $this->physicalStopOptions(),
            'appliedDate' => $date,
            'filters' => [
                'q' => $search,
                'date' => $date,
                'city' => $city,
                'physical_stop' => $physicalStopUuid,
                'line_uuid' => $lineUuid,
                'status' => $status,
            ],
            'stats' => $stats,
            'permissions' => [
                'canCreate' => Gate::allows('create', Departure::class),
                'canManage' => Gate::allows('update', new Departure),
                'canCancel' => Gate::allows('delete', new Departure),
            ],
        ]);
    }

    public function show(Request $request, Departure $departure, DepartureDetailService $detail): Response
    {
        Gate::authorize('view', $departure);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return inertia('departures/detail', $detail->payload($departure, $user));
    }

    public function store(StoreDepartureRequest $request, CreateDepartureAction $createDeparture): RedirectResponse
    {
        $createDeparture->execute($request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Départ programmé avec succès.')]);

        return to_route('departures.index');
    }

    public function update(UpdateDepartureRequest $request, Departure $departure, UpdateDepartureAction $updateDeparture): RedirectResponse
    {
        $updateDeparture->execute($departure, $request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Départ mis à jour avec succès.')]);

        return to_route('departures.index');
    }

    public function changeStatus(ChangeDepartureStatusRequest $request, Departure $departure, ChangeDepartureStatusAction $changeStatus): RedirectResponse
    {
        $changeStatus->execute($departure, $request->status());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut du départ mis à jour.')]);

        return back();
    }

    public function recordDelay(RecordDepartureDelayRequest $request, Departure $departure, RecordDepartureDelayAction $recordDepartureDelay): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $data = $request->payload();

        $recordDepartureDelay->handle(
            departure: $departure,
            actor: $user,
            delayMinutes: $data['delay_minutes'],
            reason: $data['delay_reason'],
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Retard du départ enregistré.')]);

        return back();
    }

    /** @return array<string, mixed> */
    private function serializeDeparture(Departure $departure, DepartureOccupancyService $occupancyService): array
    {
        $seatCount = $departure->bus->seat_count ?? 0;
        $occupancy = $occupancyService->occupancy($departure, $seatCount);
        $reservationsCount = $occupancy['passengers'];
        $fillRate = $occupancy['peakFillRate'];

        // Origine/destination réelles : l'ordre de circulation du départ
        // (stop_times) prime sur l'ordre de définition de la ligne — un
        // départ retour circule Abidjan → Cotonou sur une ligne définie
        // Cotonou → Abidjan.
        $tripStops = $departure->tripStops();
        $originStop = $tripStops->first();
        $destinationStop = $tripStops->last();

        return [
            'uuid' => $departure->uuid,
            'lineUuid' => $departure->line?->uuid,
            'lineCode' => $departure->line->code ?? '—',
            'lineName' => $departure->line->name ?? '—',
            'originCity' => $originStop?->physicalStop->city ?? $originStop?->city,
            'destinationCity' => $destinationStop?->physicalStop->city ?? $destinationStop?->city,
            'originPoint' => $originStop?->physicalStop->name ?? $originStop?->name,
            'isReturn' => $departure->isReturnTrip(),
            'busUuid' => $departure->bus?->uuid,
            'busRegistration' => $departure->bus->registration_number ?? 'À affecter',
            'busSeatCount' => $seatCount,
            'departureDate' => $departure->departure_date?->format('Y-m-d'),
            'departureTime' => substr((string) $departure->departure_time, 0, 5),
            'delayMinutes' => (int) $departure->delay_minutes,
            'delayReason' => $departure->delay_reason,
            'delayRecordedAt' => $departure->delay_recorded_at?->toIso8601String(),
            'estimatedArrivalAt' => $departure->estimated_arrival_at?->toIso8601String(),
            'driverUuid' => $departure->driver?->uuid,
            'driverName' => $departure->driver?->name,
            'conductorUuid' => $departure->conductor?->uuid,
            'conductorName' => $departure->conductor?->name,
            'fillRate' => $fillRate,
            'averageFillRate' => $occupancy['averageFillRate'],
            'peakLegLabel' => $occupancy['peakLegLabel'],
            'peakLoad' => $occupancy['peakLoad'],
            'reservationsCount' => $reservationsCount,
            'status' => (string) $departure->getRawOriginal('status'),
        ];
    }

    /**
     * Signale un no-show depuis la fiche départ (ops web, sans terminal
     * P501 — `terminal_p501_id` reste null sur l'événement).
     */
    public function noShow(RecordPassengerNoShowRequest $request, Departure $departure, ProcessIndividualNoShowAction $noShow): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $event = $noShow->execute($departure, null, $user, $request->payload()['ticket_uuid'], (string) Str::uuid(), now());
        } catch (SeatReleaseTicketUnknownException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Billet introuvable.')]);

            return back();
        }

        $this->flashSeatReleaseResult($event, __('No-show enregistré — tronçons libérés.'));

        return back();
    }

    /**
     * Descente anticipée depuis la fiche départ : libère la portion non
     * parcourue du billet à partir de l'arrêt réel choisi.
     */
    public function earlyExit(RecordEarlyExitRequest $request, Departure $departure, ProcessSeatReleaseAction $release): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $payload = $request->payload();
        $actualStop = Stop::query()
            ->where('uuid', $payload['stop_uuid'])
            ->where('line_id', $departure->line_id)
            ->firstOrFail();

        try {
            $event = $release->execute($departure, null, $user, $payload['ticket_uuid'], $actualStop, (string) Str::uuid(), now());
        } catch (SeatReleaseTicketUnknownException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Billet introuvable.')]);

            return back();
        }

        $this->flashSeatReleaseResult($event, __('Descente anticipée enregistrée — tronçons libérés.'));

        return back();
    }

    /**
     * Clôture no-show du départ depuis la fiche : chaque billet encore
     * « ticketed » devient no-show (sièges remis en vente). Idempotent —
     * une relecture retombe sur les mêmes événements dérivés.
     */
    public function closeNoShows(CloseDepartureNoShowsRequest $request, Departure $departure, CloseNoShowsForDepartureAction $close): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (! in_array((string) $departure->getRawOriginal('status'), ['departed', 'arrived'], true)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('La clôture no-show exige un départ en route ou arrivé.'),
            ]);

            return back();
        }

        $events = $close->execute($departure, null, $user, (string) Str::uuid(), now());

        Inertia::flash('toast', $events->isNotEmpty()
            ? ['type' => 'success', 'message' => __(':count no-show clôturé(s) — tronçons libérés.', ['count' => $events->count()])]
            : ['type' => 'info', 'message' => __('Aucun billet à clôturer — tout le monde a été contrôlé.')]);

        return back();
    }

    /**
     * Annulation exceptionnelle d'un embarquement depuis la fiche
     * (erreur de scan P501). Motif obligatoire, audit immuable — le
     * segment repasse à « ticketed » et redevient scannable.
     */
    public function reverseBoarding(ReverseDepartureBoardingRequest $request, Departure $departure, ReverseBoardingEventAction $reverse): RedirectResponse
    {
        $payload = $request->payload();

        $event = BoardingEvent::query()
            ->where('departure_id', $departure->id)
            ->where('status', BoardingEventStatus::Accepted)
            ->whereHas('ticket', fn ($q) => $q->where('uuid', $payload['ticket_uuid']))
            ->latest('server_recorded_at')
            ->first();

        if ($event === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Aucun embarquement accepté à annuler pour ce billet.'),
            ]);

            return back();
        }

        $reverse->execute($event, $request->user(), $payload['reason']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Embarquement annulé — le billet redevient contrôlable.'),
        ]);

        return back();
    }

    /**
     * L'événement peut être accepté, rejeté (règle métier) ou en conflit
     * (libération concurrente depuis un terminal P501) — le toast reflète
     * l'issue réelle au lieu d'annoncer un succès systématique.
     */
    private function flashSeatReleaseResult(SeatReleaseEvent $event, string $successMessage): void
    {
        if ($event->status === SeatReleaseEventStatus::Accepted) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $successMessage]);

            return;
        }

        $message = $event->conflict_payload['message'] ?? __('La libération a été refusée.');
        $type = $event->status === SeatReleaseEventStatus::Conflict ? 'error' : 'error';

        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }

    /** @return array<int, array{uuid: string, label: string}> */
    private function lineOptions(): array
    {
        return Line::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get()
            ->map(fn (Line $line): array => [
                'uuid' => (string) $line->uuid,
                'label' => $line->code.' · '.$line->name,
            ])
            ->all();
    }

    /** @return array<int, string> */
    private function cityOptions(): array
    {
        return PhysicalStop::query()
            ->where('status', 'active')
            ->whereHas('stops')
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->all();
    }

    /** @return array<int, array{uuid: string, label: string}> */
    private function physicalStopOptions(): array
    {
        return PhysicalStop::query()
            ->where('status', 'active')
            ->whereHas('stops')
            ->orderBy('city')
            ->orderBy('name')
            ->get(['uuid', 'name', 'city'])
            ->map(fn (PhysicalStop $stop): array => [
                'uuid' => (string) $stop->uuid,
                'label' => $stop->name.' · '.$stop->city,
            ])
            ->all();
    }

    /** @return array{total: int, scheduled: int, boarding: int, departed: int, arrived: int, cancelled: int, punctuality: float|null, fillRate: float|null, seatsSold: int} */
    private function buildStats(string $date): array
    {
        $departures = Departure::query()
            ->whereDate('departure_date', $date)
            ->with(['bus:id,seat_count', 'line:id'])
            ->get(['id', 'status', 'delay_minutes', 'bus_id', 'line_id']);

        $lineIds = $departures->pluck('line_id')->filter()->unique()->values();
        $ordersByLine = Stop::query()
            ->whereIn('line_id', $lineIds)
            ->where('status', 'active')
            ->orderBy('stop_order')
            ->get(['line_id', 'stop_order'])
            ->groupBy('line_id')
            ->map(fn ($stops) => $stops->pluck('stop_order')->map(fn ($order) => (int) $order)->values()->all());

        $segments = ReservationSegment::query()
            ->whereIn('departure_id', $departures->modelKeys())
            ->whereIn('status', ReservationSegmentStatus::occupyingValues())
            ->get(['departure_id', 'reservation_id', 'origin_stop_order', 'destination_stop_order'])
            ->groupBy('departure_id');

        $seatCapacity = 0;
        $seatLegs = 0;
        $passengerLegs = 0;
        $seatsSold = 0;

        foreach ($departures as $departure) {
            $seatCount = (int) ($departure->bus->seat_count ?? 0);
            $orders = $ordersByLine->get($departure->line_id) ?? [];
            $legCount = max(0, count($orders) - 1);
            $positionOf = array_flip($orders);
            $departureSegments = $segments->get($departure->id) ?? collect();

            $seatCapacity += $seatCount;
            $seatLegs += $legCount * $seatCount;
            $seatsSold += $departureSegments->pluck('reservation_id')->unique()->count();

            foreach ($departureSegments as $segment) {
                $from = $positionOf[(int) $segment->origin_stop_order] ?? null;
                $to = $positionOf[(int) $segment->destination_stop_order] ?? null;
                if ($from === null || $to === null || $to <= $from) {
                    continue;
                }
                $passengerLegs += $to - $from;
            }
        }
        $completed = $departures->filter(fn (Departure $departure) => in_array(
            (string) $departure->getRawOriginal('status'),
            [DepartureStatus::Departed->value, DepartureStatus::Arrived->value],
            true,
        ));
        $onTime = $completed->filter(fn (Departure $departure) => (int) $departure->delay_minutes <= 15)->count();

        return [
            'total' => $departures->count(),
            'scheduled' => $departures->filter(fn (Departure $d) => (string) $d->getRawOriginal('status') === DepartureStatus::Scheduled->value)->count(),
            'boarding' => $departures->filter(fn (Departure $d) => (string) $d->getRawOriginal('status') === DepartureStatus::Boarding->value)->count(),
            'departed' => $departures->filter(fn (Departure $d) => (string) $d->getRawOriginal('status') === DepartureStatus::Departed->value)->count(),
            'arrived' => $departures->filter(fn (Departure $d) => (string) $d->getRawOriginal('status') === DepartureStatus::Arrived->value)->count(),
            'cancelled' => $departures->filter(fn (Departure $d) => (string) $d->getRawOriginal('status') === DepartureStatus::Cancelled->value)->count(),
            'punctuality' => $completed->isEmpty() ? null : round($onTime / $completed->count() * 100, 1),
            'fillRate' => $seatLegs > 0 ? round($passengerLegs / $seatLegs * 100, 1) : null,
            'seatsSold' => $seatsSold,
        ];
    }
}
