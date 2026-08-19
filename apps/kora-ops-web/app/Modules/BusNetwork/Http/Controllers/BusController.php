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
}
