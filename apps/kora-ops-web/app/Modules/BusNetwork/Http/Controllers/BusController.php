<?php

namespace App\Modules\BusNetwork\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Actions\ChangeBusStatusAction;
use App\Modules\BusNetwork\Actions\CreateBusAction;
use App\Modules\BusNetwork\Actions\UpdateBusAction;
use App\Modules\BusNetwork\Data\BusStatus;
use App\Modules\BusNetwork\Http\Requests\ChangeBusStatusRequest;
use App\Modules\BusNetwork\Http\Requests\StoreBusRequest;
use App\Modules\BusNetwork\Http\Requests\UpdateBusRequest;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\BusModel;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Bus::class);
        $query = Bus::query()->with(['agency', 'busModel', 'composition'])->orderBy('registration_number');
        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(registration_number) LIKE ?', [$term])
                    ->orWhereHas('busModel', fn ($model) => $model->whereRaw('LOWER(brand) LIKE ?', [$term])->orWhereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('agency', fn ($agency) => $agency->whereRaw('LOWER(name) LIKE ?', [$term]));
            });
        }
        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, array_column(BusStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        $agencyId = $request->integer('agency_id');
        if ($agencyId > 0) {
            $query->where('agency_id', $agencyId);
        }

        return inertia('fleet', [
            'buses' => $query->paginate(15)->through(fn (Bus $bus): array => $this->serializeBus($bus)),
            'statuses' => BusStatus::options(),
            'agencies' => Agency::query()->where('status', 'active')->orderBy('name')->get()->map(fn (Agency $agency): array => ['id' => $agency->id, 'name' => $agency->name]),
            'models' => BusModel::query()->where('status', 'active')->orderBy('brand')->orderBy('name')->get()->map(fn (BusModel $model): array => ['id' => $model->id, 'label' => $model->brand.' '.$model->name]),
            'compositions' => BusComposition::query()->where('status', 'active')->orderBy('seat_count')->get()->map(fn (BusComposition $composition): array => ['id' => $composition->id, 'label' => $composition->name, 'seatCount' => $composition->seat_count]),
            'permissions' => [
                'canCreate' => Gate::allows('create', Bus::class),
                'canUpdate' => Gate::allows('update', new Bus),
                'canDeactivate' => Gate::allows('deactivate', new Bus),
            ],
        ]);
    }

    public function show(Request $request, Bus $bus): Response
    {
        Gate::authorize('view', $bus);

        $bus->load([
            'agency',
            'busModel',
            'composition',
            'seats' => fn ($q) => $q->orderByRaw('LENGTH(code), code'),
            'departures' => fn ($q) => $q->with('line:id,uuid,code,name')->latest('departure_date')->latest('departure_time')->limit(10),
        ]);

        return inertia('fleet/detail', [
            'bus' => $this->serializeBusDetail($bus),
            'statuses' => BusStatus::options(),
            'agencies' => Agency::query()->where('status', 'active')->orderBy('name')->get()->map(fn (Agency $agency): array => ['id' => $agency->id, 'name' => $agency->name]),
            'models' => BusModel::query()->where('status', 'active')->orderBy('brand')->orderBy('name')->get()->map(fn (BusModel $model): array => ['id' => $model->id, 'label' => $model->brand.' '.$model->name]),
            'compositions' => BusComposition::query()->where('status', 'active')->orderBy('seat_count')->get()->map(fn (BusComposition $composition): array => ['id' => $composition->id, 'label' => $composition->name, 'seatCount' => $composition->seat_count]),
            'timeline' => $this->buildTimeline($bus),
            'permissions' => [
                'canUpdate' => Gate::allows('update', $bus),
                'canDeactivate' => Gate::allows('deactivate', $bus),
            ],
        ]);
    }

    public function store(StoreBusRequest $request, CreateBusAction $createBus): RedirectResponse
    {
        $createBus->execute($request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bus créé avec succès.')]);

        return to_route('fleet.index');
    }

    public function update(UpdateBusRequest $request, Bus $bus, UpdateBusAction $updateBus): RedirectResponse
    {
        $updateBus->execute($bus, $request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bus mis à jour avec succès.')]);

        return to_route('fleet.index');
    }

    public function changeStatus(ChangeBusStatusRequest $request, Bus $bus, ChangeBusStatusAction $changeBusStatus): RedirectResponse
    {
        $changeBusStatus->execute($bus, $request->status());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut du bus mis à jour.')]);

        return to_route('fleet.index');
    }

    /** @return array<string, mixed> */
    private function serializeBus(Bus $bus): array
    {
        return [
            'uuid' => $bus->uuid,
            'registrationNumber' => $bus->registration_number,
            'modelId' => $bus->bus_model_id,
            'modelName' => $bus->busModel?->brand.' '.$bus->busModel?->name,
            'compositionId' => $bus->composition_id,
            'compositionName' => $bus->composition?->name,
            'seatCount' => $bus->seat_count,
            'agencyId' => $bus->agency_id,
            'agencyName' => $bus->agency?->name,
            'status' => BusStatus::from((string) $bus->getRawOriginal('status'))->value,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeBusDetail(Bus $bus): array
    {
        $seats = [];
        foreach ($bus->seats as $seat) {
            $seats[] = [
                'uuid' => $seat->uuid,
                'code' => $seat->code,
                'type' => (string) $seat->getRawOriginal('type'),
                'sellable' => (bool) $seat->sellable,
                'status' => (string) $seat->getRawOriginal('status'),
            ];
        }

        $departures = [];
        foreach ($bus->departures as $departure) {
            $departures[] = [
                'uuid' => $departure->uuid,
                'lineCode' => $departure->line ? $departure->line->code : '—',
                'lineName' => $departure->line ? $departure->line->name : '—',
                'departureDate' => $departure->departure_date,
                'departureTime' => $departure->departure_time,
                'estimatedArrivalAt' => $departure->estimated_arrival_at,
                'status' => (string) $departure->getRawOriginal('status'),
            ];
        }

        $brand = $bus->busModel ? $bus->busModel->brand : ($bus->brand ?? '—');
        $model = $bus->busModel ? $bus->busModel->name : ($bus->model ?? '—');
        $modelName = $bus->busModel ? ($bus->busModel->brand.' '.$bus->busModel->name) : '—';
        $compositionName = $bus->composition ? $bus->composition->name : '—';
        $compositionCode = $bus->composition ? $bus->composition->code : null;
        $layout = $bus->composition ? $bus->composition->layout : [];
        $agencyName = $bus->agency ? $bus->agency->name : '—';
        $agencyCity = $bus->agency ? $bus->agency->city : null;
        $agencyCountry = $bus->agency ? $bus->agency->country : null;

        return [
            'uuid' => $bus->uuid,
            'registrationNumber' => $bus->registration_number,
            'brand' => $brand,
            'model' => $model,
            'modelId' => $bus->bus_model_id,
            'modelName' => $modelName,
            'compositionId' => $bus->composition_id,
            'compositionCode' => $compositionCode,
            'compositionName' => $compositionName,
            'layout' => $layout,
            'seatCount' => $bus->seat_count,
            'agencyId' => $bus->agency_id,
            'agencyName' => $agencyName,
            'agencyCity' => $agencyCity,
            'agencyCountry' => $agencyCountry,
            'status' => BusStatus::from((string) $bus->getRawOriginal('status'))->value,
            'seats' => $seats,
            'departures' => $departures,
            'createdAt' => $bus->created_at?->toIso8601String(),
            'updatedAt' => $bus->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Build a lightweight timeline of key bus events.
     *
     * @return array<int, array{label: string, date: string|null}>
     */
    private function buildTimeline(Bus $bus): array
    {
        $events = [];

        if ($bus->created_at !== null) {
            $events[] = [
                'label' => 'Véhicule enregistré',
                'date' => $bus->created_at->toIso8601String(),
            ];
        }

        if ($bus->seats->isNotEmpty()) {
            $firstSeat = $bus->seats->first();
            if ($firstSeat->created_at !== null) {
                $events[] = [
                    'label' => 'Plan de sièges synchronisé',
                    'date' => $firstSeat->created_at->toIso8601String(),
                ];
            }
        }

        if ($bus->updated_at !== null && $bus->created_at !== null && $bus->updated_at->notEqualTo($bus->created_at)) {
            $events[] = [
                'label' => 'Dernière mise à jour',
                'date' => $bus->updated_at->toIso8601String(),
            ];
        }

        return $events;
    }
}
