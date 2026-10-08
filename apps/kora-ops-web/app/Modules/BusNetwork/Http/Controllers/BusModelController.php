<?php

namespace App\Modules\BusNetwork\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Actions\ChangeBusModelStatusAction;
use App\Modules\BusNetwork\Actions\CreateBusModelAction;
use App\Modules\BusNetwork\Actions\UpdateBusModelAction;
use App\Modules\BusNetwork\Data\BusModelStatus;
use App\Modules\BusNetwork\Http\Requests\ChangeBusModelStatusRequest;
use App\Modules\BusNetwork\Http\Requests\StoreBusModelRequest;
use App\Modules\BusNetwork\Http\Requests\UpdateBusModelRequest;
use App\Modules\BusNetwork\Models\BusModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusModelController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', BusModel::class);

        $query = BusModel::query()->withCount('buses')->ordered();

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(brand) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term]);
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, array_column(BusModelStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        return inertia('models', [
            'models' => $query->paginate(15)->through(fn (BusModel $model): array => $this->serializeModel($model)),
            'statuses' => BusModelStatus::options(),
            'permissions' => [
                'canCreate' => Gate::allows('create', BusModel::class),
                'canUpdate' => Gate::allows('update', new BusModel),
                'canDeactivate' => Gate::allows('deactivate', new BusModel),
            ],
        ]);
    }

    public function store(StoreBusModelRequest $request, CreateBusModelAction $createModel): RedirectResponse
    {
        $createModel->execute($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modèle créé avec succès.')]);

        return to_route('models.index');
    }

    public function update(
        UpdateBusModelRequest $request,
        BusModel $model,
        UpdateBusModelAction $updateModel,
    ): RedirectResponse {
        $updateModel->execute($model, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Modèle mis à jour avec succès.')]);

        return to_route('models.index');
    }

    public function changeStatus(
        ChangeBusModelStatusRequest $request,
        BusModel $model,
        ChangeBusModelStatusAction $changeStatus,
    ): RedirectResponse {
        $changeStatus->execute($model, $request->status());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut du modèle mis à jour.')]);

        return to_route('models.index');
    }

    /** @return array<string, mixed> */
    private function serializeModel(BusModel $model): array
    {
        return [
            'uuid' => $model->uuid,
            'brand' => $model->brand,
            'name' => $model->name,
            'defaultSeatCount' => $model->default_seat_count,
            'busesCount' => $model->buses_count,
            'status' => BusModelStatus::from((string) $model->getRawOriginal('status'))->value,
            'createdAt' => $model->created_at?->toISOString(),
            'updatedAt' => $model->updated_at?->toISOString(),
        ];
    }
}
