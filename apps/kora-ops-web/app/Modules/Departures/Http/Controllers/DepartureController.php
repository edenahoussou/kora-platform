<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Actions\ChangeDepartureStatusAction;
use App\Modules\Departures\Actions\CreateDepartureAction;
use App\Modules\Departures\Actions\UpdateDepartureAction;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Http\Requests\ChangeDepartureStatusRequest;
use App\Modules\Departures\Http\Requests\StoreDepartureRequest;
use App\Modules\Departures\Http\Requests\UpdateDepartureRequest;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepartureController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Departure::class);

        $query = Departure::query()
            ->with(['line:id,uuid,code,name', 'bus:id,uuid,registration_number,seat_count', 'agency:id,uuid,name,city', 'driver:id,name', 'conductor:id,name'])
            ->orderBy('departure_date', 'desc')
            ->orderBy('departure_time');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereHas('line', fn ($line) => $line->whereRaw('LOWER(code) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('bus', fn ($bus) => $bus->whereRaw('LOWER(registration_number) LIKE ?', [$term]))
                    ->orWhereHas('agency', fn ($agency) => $agency->whereRaw('LOWER(name) LIKE ?', [$term]));
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, DepartureStatus::values(), true)) {
            $query->where('status', $status);
        }

        $agencyId = $request->integer('agency_id');
        if ($agencyId > 0) {
            $query->where('agency_id', $agencyId);
        }

        $lineId = $request->integer('line_id');
        if ($lineId > 0) {
            $query->where('line_id', $lineId);
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
            'lines' => Line::query()->where('status', 'active')->orderBy('code')->get()->map(fn (Line $line): array => ['id' => $line->id, 'label' => $line->code.' · '.$line->name]),
            'agencies' => Agency::query()->where('status', 'active')->orderBy('name')->get()->map(fn (Agency $agency): array => ['id' => $agency->id, 'name' => $agency->name]),
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
            'bus:id,uuid,registration_number,seat_count,agency_id',
            'bus.agency:id,uuid,name,city',
            'bus.seats' => fn ($q) => $q->orderByRaw('LENGTH(code), code'),
            'agency:id,uuid,name,city,country',
            'driver:id,name',
            'conductor:id,name',
            'creator:id,name',
        ]);

        return inertia('departures/detail', [
            'departure' => $this->serializeDepartureDetail($departure),
            'statuses' => DepartureStatus::options(),
            'lines' => Line::query()->where('status', 'active')->orderBy('code')->get()->map(fn (Line $line): array => ['id' => $line->id, 'label' => $line->code.' · '.$line->name]),
            'agencies' => Agency::query()->where('status', 'active')->orderBy('name')->get()->map(fn (Agency $agency): array => ['id' => $agency->id, 'name' => $agency->name]),
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

    /** @return array<string, mixed> */
    private function serializeDeparture(Departure $departure): array
    {
        $seatCount = $departure->bus->seat_count ?? 0;
        $reservationsCount = $departure->reservations()->count();
        $fillRate = $seatCount > 0 ? round($reservationsCount / $seatCount * 100) : 0;

        return [
            'uuid' => $departure->uuid,
            'lineId' => $departure->line_id,
            'lineCode' => $departure->line->code ?? '—',
            'lineName' => $departure->line->name ?? '—',
            'busId' => $departure->bus_id,
            'busRegistration' => $departure->bus->registration_number ?? 'À affecter',
            'busSeatCount' => $seatCount,
            'agencyId' => $departure->agency_id,
            'agencyName' => $departure->agency->name ?? '—',
            'agencyCity' => $departure->agency->city ?? null,
            'departureDate' => $departure->departure_date?->format('Y-m-d'),
            'departureTime' => $departure->departure_time,
            'estimatedArrivalAt' => $departure->estimated_arrival_at?->toIso8601String(),
            'driverId' => $departure->driver_id,
            'driverName' => $departure->driver?->name,
            'conductorId' => $departure->conductor_id,
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
            'lineId' => $departure->line_id,
            'lineCode' => $departure->line->code ?? '—',
            'lineName' => $departure->line->name ?? '—',
            'lineEstimatedDuration' => $departure->line->estimated_duration_minutes,
            'stops' => $stops,
            'busId' => $departure->bus_id,
            'busUuid' => $departure->bus->uuid,
            'busRegistration' => $departure->bus->registration_number ?? 'À affecter',
            'busSeatCount' => $seatCount,
            'busAgencyName' => $departure->bus->agency?->name,
            'seats' => $seats,
            'agencyId' => $departure->agency_id,
            'agencyName' => $departure->agency->name ?? '—',
            'agencyCity' => $departure->agency->city,
            'agencyCountry' => $departure->agency->country,
            'departureDate' => $departure->departure_date?->format('Y-m-d'),
            'departureTime' => $departure->departure_time,
            'estimatedArrivalAt' => $departure->estimated_arrival_at?->toIso8601String(),
            'driverId' => $departure->driver_id,
            'driverName' => $departure->driver?->name,
            'conductorId' => $departure->conductor_id,
            'conductorName' => $departure->conductor?->name,
            'creatorName' => $departure->creator?->name,
            'fillRate' => $fillRate,
            'reservationsCount' => $reservationsCount,
            'status' => (string) $departure->getRawOriginal('status'),
            'createdAt' => $departure->created_at?->toIso8601String(),
            'updatedAt' => $departure->updated_at?->toIso8601String(),
        ];
    }

    /** @return Collection<int, array{id: int, label: string, agencyId: int|null}> */
    private function busOptions(): Collection
    {
        return Bus::query()
            ->where('status', 'active')
            ->orderBy('registration_number')
            ->get()
            ->map(fn (Bus $bus): array => [
                'id' => $bus->id,
                'label' => $bus->registration_number,
                'agencyId' => $bus->agency_id,
            ]);
    }

    /** @return Collection<int, array{id: int, label: string}> */
    private function userOptions(string $role): Collection
    {
        return User::query()
            ->role($role)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => ['id' => $user->id, 'label' => $user->name]);
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
