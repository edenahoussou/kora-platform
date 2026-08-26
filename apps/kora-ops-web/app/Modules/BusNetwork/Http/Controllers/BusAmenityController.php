<?php

namespace App\Modules\BusNetwork\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Actions\ChangeBusAmenityStatusAction;
use App\Modules\BusNetwork\Actions\CreateBusAmenityAction;
use App\Modules\BusNetwork\Actions\UpdateBusAmenityAction;
use App\Modules\BusNetwork\Data\BusAmenityStatus;
use App\Modules\BusNetwork\Http\Requests\ChangeBusAmenityStatusRequest;
use App\Modules\BusNetwork\Http\Requests\StoreBusAmenityRequest;
use App\Modules\BusNetwork\Http\Requests\UpdateBusAmenityRequest;
use App\Modules\BusNetwork\Models\BusAmenity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusAmenityController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', BusAmenity::class);

        $query = BusAmenity::query()->withCount('buses')->ordered();

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(label) LIKE ?', [$term]);
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, array_column(BusAmenityStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        return inertia('amenities', [
            'amenities' => $query->paginate(15)->through(fn (BusAmenity $amenity): array => $this->serializeAmenity($amenity)),
            'statuses' => BusAmenityStatus::options(),
            'permissions' => [
                'canCreate' => Gate::allows('create', BusAmenity::class),
                'canUpdate' => Gate::allows('update', new BusAmenity),
                'canDeactivate' => Gate::allows('deactivate', new BusAmenity),
            ],
        ]);
    }

    public function store(StoreBusAmenityRequest $request, CreateBusAmenityAction $createAmenity): RedirectResponse
    {
        $createAmenity->execute($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Commodité créée avec succès.')]);

        return to_route('amenities.index');
    }

    public function update(
        UpdateBusAmenityRequest $request,
        BusAmenity $amenity,
        UpdateBusAmenityAction $updateAmenity,
    ): RedirectResponse {
        $updateAmenity->execute($amenity, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Commodité mise à jour avec succès.')]);

        return to_route('amenities.index');
    }

    public function changeStatus(
        ChangeBusAmenityStatusRequest $request,
        BusAmenity $amenity,
        ChangeBusAmenityStatusAction $changeStatus,
    ): RedirectResponse {
        $changeStatus->execute($amenity, $request->status());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut de la commodité mis à jour.')]);

        return to_route('amenities.index');
    }

    /** @return array<string, mixed> */
    private function serializeAmenity(BusAmenity $amenity): array
    {
        return [
            'uuid' => $amenity->uuid,
            'code' => $amenity->code,
            'label' => $amenity->label,
            'icon' => $amenity->icon,
            'busesCount' => $amenity->buses_count,
            'status' => BusAmenityStatus::from((string) $amenity->getRawOriginal('status'))->value,
            'createdAt' => $amenity->created_at?->toISOString(),
            'updatedAt' => $amenity->updated_at?->toISOString(),
        ];
    }
}
