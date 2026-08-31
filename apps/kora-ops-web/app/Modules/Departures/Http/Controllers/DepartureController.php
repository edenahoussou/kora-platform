<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Actions\ChangeDepartureStatusAction;
use App\Modules\Departures\Actions\CreateDepartureAction;
use App\Modules\Departures\Actions\RecordDepartureDelayAction;
use App\Modules\Departures\Actions\UpdateDepartureAction;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Http\Requests\ChangeDepartureStatusRequest;
use App\Modules\Departures\Http\Requests\RecordDepartureDelayRequest;
use App\Modules\Departures\Http\Requests\StoreDepartureRequest;
use App\Modules\Departures\Http\Requests\UpdateDepartureRequest;
use App\Modules\Departures\Models\Departure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepartureController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Departure::class);

        $query = Departure::query()
            ->with(['line:id,uuid,code,name', 'bus:id,uuid,registration_number,seat_count', 'driver:id,uuid,name', 'conductor:id,uuid,name'])
            ->orderBy('departure_date', 'desc')
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

        $date = $request->string('date')->trim()->value();
        if ($date !== '') {
            $query->whereDate('departure_date', $date);
        }

        $paginated = $query->paginate(15);

        $stats = $this->buildStats($date);

        return inertia('departures', [
            'departures' => $paginated->through(fn (Departure $departure): array => $this->serializeDeparture($departure)),
            'statuses' => DepartureStatus::options(),
            'lines' => $this->lineOptions(),
            'buses' => $this->busOptions(),
            'drivers' => $this->userOptions('bus_driver'),
            'conductors' => $this->userOptions('conductor'),
            'stats' => $stats,
            'permissions' => [
                'canCreate' => Gate::allows('create', Departure::class),
                'canManage' => Gate::allows('update', new Departure),
                'canCancel' => Gate::allows('delete', new Departure),
            ],
        ]);
    }

    public function show(Request $request, Departure $departure): Response
    {
        Gate::authorize('view', $departure);

        $departure->load([
            'line.stops' => fn ($q) => $q->where('status', 'active')->orderBy('stop_order'),
            'bus:id,uuid,registration_number,seat_count',
            'bus.seats' => fn ($q) => $q->orderByRaw('LENGTH(code), code'),
            'driver:id,uuid,name',
            'conductor:id,uuid,name',
            'creator:id,name',
        ]);

        return inertia('departures/detail', [
            'departure' => $this->serializeDepartureDetail($departure),
            'programmingSource' => $departure->plannedOccurrence?->programme->uuid,
            'canProgram' => $request->user()?->can('departures.manage') ?? false,
            'statuses' => DepartureStatus::options(),
            'lines' => $this->lineOptions(),
            'buses' => $this->busOptions(),
            'drivers' => $this->userOptions('bus_driver'),
            'conductors' => $this->userOptions('conductor'),
            'timeline' => $this->buildTimeline($departure),
            'permissions' => [
                'canUpdate' => Gate::allows('update', $departure),
                'canCancel' => Gate::allows('delete', $departure),
            ],
        ]);
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
    private function serializeDeparture(Departure $departure): array
    {
        $seatCount = $departure->bus->seat_count ?? 0;
        $reservationsCount = $departure->reservations()->count();
        $fillRate = $seatCount > 0 ? round($reservationsCount / $seatCount * 100) : 0;

        return [
            'uuid' => $departure->uuid,
            'lineUuid' => $departure->line?->uuid,
            'lineCode' => $departure->line->code ?? '—',
            'lineName' => $departure->line->name ?? '—',
            'busUuid' => $departure->bus?->uuid,
            'busRegistration' => $departure->bus->registration_number ?? 'À affecter',
            'busSeatCount' => $seatCount,
            'departureDate' => $departure->departure_date?->format('Y-m-d'),
            'departureTime' => $departure->departure_time,
            'delayMinutes' => (int) $departure->delay_minutes,
            'delayReason' => $departure->delay_reason,
            'delayRecordedAt' => $departure->delay_recorded_at?->toIso8601String(),
            'estimatedArrivalAt' => $departure->estimated_arrival_at?->toIso8601String(),
            'driverUuid' => $departure->driver?->uuid,
            'driverName' => $departure->driver?->name,
            'conductorUuid' => $departure->conductor?->uuid,
            'conductorName' => $departure->conductor?->name,
            'fillRate' => $fillRate,
            'reservationsCount' => $reservationsCount,
            'status' => (string) $departure->getRawOriginal('status'),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeDepartureDetail(Departure $departure): array
    {
        $stops = [];
        foreach ($departure->line->stops ?? [] as $stop) {
            $stops[] = [
                'uuid' => $stop->uuid,
                'name' => $stop->name,
                'stopOrder' => $stop->stop_order,
                'city' => $stop->city,
            ];
        }

        $seats = [];
        foreach ($departure->bus->seats ?? [] as $seat) {
            $seats[] = [
                'uuid' => $seat->uuid,
                'code' => $seat->code,
                'type' => (string) $seat->getRawOriginal('type'),
                'sellable' => (bool) $seat->sellable,
                'status' => (string) $seat->getRawOriginal('status'),
            ];
        }

        $seatCount = $departure->bus->seat_count ?? 0;
        $reservationsCount = $departure->reservations()->count();
        $fillRate = $seatCount > 0 ? round($reservationsCount / $seatCount * 100) : 0;

        return [
            'uuid' => $departure->uuid,
            'lineUuid' => $departure->line?->uuid,
            'lineCode' => $departure->line->code ?? '—',
            'lineName' => $departure->line->name ?? '—',
            'lineEstimatedDuration' => $departure->line->estimated_duration_minutes,
            'stops' => $stops,
            'busUuid' => $departure->bus?->uuid,
            'busRegistration' => $departure->bus->registration_number ?? 'À affecter',
            'busSeatCount' => $seatCount,
            'seats' => $seats,
            'departureDate' => $departure->departure_date?->format('Y-m-d'),
            'departureTime' => $departure->departure_time,
            'delayMinutes' => (int) $departure->delay_minutes,
            'delayReason' => $departure->delay_reason,
            'delayRecordedAt' => $departure->delay_recorded_at?->toIso8601String(),
            'estimatedArrivalAt' => $departure->estimated_arrival_at?->toIso8601String(),
            'driverUuid' => $departure->driver?->uuid,
            'driverName' => $departure->driver?->name,
            'conductorUuid' => $departure->conductor?->uuid,
            'conductorName' => $departure->conductor?->name,
            'creatorName' => $departure->creator?->name,
            'fillRate' => $fillRate,
            'reservationsCount' => $reservationsCount,
            'status' => (string) $departure->getRawOriginal('status'),
            'createdAt' => $departure->created_at?->toIso8601String(),
            'updatedAt' => $departure->updated_at?->toIso8601String(),
        ];
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

    /** @return array<int, array{uuid: string, label: string}> */
    private function busOptions(): array
    {
        return Bus::query()
            ->where('status', 'active')
            ->orderBy('registration_number')
            ->get()
            ->map(fn (Bus $bus): array => [
                'uuid' => (string) $bus->uuid,
                'label' => (string) $bus->registration_number,
            ])
            ->all();
    }

    /** @return array<int, array{uuid: string, label: string}> */
    private function userOptions(string $role): array
    {
        return User::query()
            ->role($role)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'uuid' => (string) $user->uuid,
                'label' => (string) $user->name,
            ])
            ->all();
    }

    /** @return array{total: int, scheduled: int, boarding: int, departed: int, arrived: int, cancelled: int} */
    private function buildStats(?string $date): array
    {
        $query = Departure::query();
        if ($date !== null && $date !== '') {
            $query->whereDate('departure_date', $date);
        }

        return [
            'total' => $query->count(),
            'scheduled' => (clone $query)->where('status', DepartureStatus::Scheduled->value)->count(),
            'boarding' => (clone $query)->where('status', DepartureStatus::Boarding->value)->count(),
            'departed' => (clone $query)->where('status', DepartureStatus::Departed->value)->count(),
            'arrived' => (clone $query)->where('status', DepartureStatus::Arrived->value)->count(),
            'cancelled' => (clone $query)->where('status', DepartureStatus::Cancelled->value)->count(),
        ];
    }

    /**
     * @return array<int, array{label: string, date: string|null}>
     */
    private function buildTimeline(Departure $departure): array
    {
        $events = [];

        if ($departure->created_at !== null) {
            $events[] = [
                'label' => 'Départ programmé',
                'date' => $departure->created_at->toIso8601String(),
            ];
        }

        if ($departure->updated_at !== null && $departure->created_at !== null && $departure->updated_at->notEqualTo($departure->created_at)) {
            $events[] = [
                'label' => 'Dernière mise à jour',
                'date' => $departure->updated_at->toIso8601String(),
            ];
        }

        return $events;
    }
}
