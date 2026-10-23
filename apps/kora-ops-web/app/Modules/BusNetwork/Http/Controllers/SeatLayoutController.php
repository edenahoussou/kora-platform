<?php

namespace App\Modules\BusNetwork\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Data\CompositionStatus;
use App\Modules\BusNetwork\Data\SeatLayout;
use App\Modules\BusNetwork\Http\Requests\ChangeSeatLayoutStatusRequest;
use App\Modules\BusNetwork\Http\Requests\StoreSeatLayoutRequest;
use App\Modules\BusNetwork\Http\Requests\UpdateSeatLayoutRequest;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Services\SeatLayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SeatLayoutController extends Controller
{
    public function __construct(private SeatLayoutService $layouts) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', BusComposition::class);

        $templates = $this->layouts->templatesForIndex()
            ->map(fn (BusComposition $composition): array => $this->layouts->serialize($composition));

        return inertia('fleet/layouts/index', [
            'templates' => $templates,
            'stats' => [
                'total' => $templates->count(),
                'active' => $templates->where('status', CompositionStatus::Active->value)->count(),
                'drafts' => $templates->where('status', CompositionStatus::Draft->value)->count(),
                'busesEquipped' => $templates->sum('busesCount'),
                'sellableSeats' => $templates
                    ->where('status', CompositionStatus::Active->value)
                    ->sum(fn (array $template): int => SeatLayout::fromGridArray($template['grid'])->sellableCount()),
            ],
            'permissions' => [
                'canCreate' => Gate::allows('create', BusComposition::class),
                'canUpdate' => Gate::allows('update', new BusComposition),
                'canDeactivate' => Gate::allows('deactivate', new BusComposition),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', BusComposition::class);

        $source = null;
        $duplicateUuid = $request->query('duplicate');
        if (is_string($duplicateUuid) && $duplicateUuid !== '') {
            $source = BusComposition::query()->where('uuid', $duplicateUuid)->first();
        }

        return inertia('fleet/layouts/editor', [
            'duplicate' => $source ? $this->layouts->serialize($source) : null,
        ]);
    }

    public function store(StoreSeatLayoutRequest $request): RedirectResponse
    {
        $composition = $this->layouts->create($request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gabarit « :name » créé.', ['name' => $composition->name])]);

        return to_route('layouts.index');
    }

    public function edit(BusComposition $composition): Response
    {
        Gate::authorize('view', $composition);

        return inertia('fleet/layouts/editor', [
            'template' => $this->layouts->serialize($composition),
        ]);
    }

    public function update(UpdateSeatLayoutRequest $request, BusComposition $composition): RedirectResponse
    {
        $this->layouts->update($composition, $request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gabarit mis à jour.')]);

        return to_route('layouts.index');
    }

    public function duplicate(BusComposition $composition): RedirectResponse
    {
        Gate::authorize('create', BusComposition::class);

        $copy = $this->layouts->duplicate($composition);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gabarit « :name » dupliqué.', ['name' => $copy->name])]);

        return to_route('layouts.edit', $copy);
    }

    public function changeStatus(ChangeSeatLayoutStatusRequest $request, BusComposition $composition): RedirectResponse
    {
        $this->layouts->changeStatus($composition, $request->status());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut du gabarit mis à jour.')]);

        return back();
    }
}
