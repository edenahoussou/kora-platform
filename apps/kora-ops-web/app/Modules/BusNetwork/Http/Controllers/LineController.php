<?php

namespace App\Modules\BusNetwork\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Actions\ChangeLineStatusAction;
use App\Modules\BusNetwork\Actions\CreateLineAction;
use App\Modules\BusNetwork\Actions\CreateStopAction;
use App\Modules\BusNetwork\Actions\DeactivateStopAction;
use App\Modules\BusNetwork\Actions\UpdateLineAction;
use App\Modules\BusNetwork\Actions\UpdateStopAction;
use App\Modules\BusNetwork\Data\LineStatus;
use App\Modules\BusNetwork\Data\LineType;
use App\Modules\BusNetwork\Http\Requests\StoreLineRequest;
use App\Modules\BusNetwork\Http\Requests\StoreStopRequest;
use App\Modules\BusNetwork\Http\Requests\UpdateLineRequest;
use App\Modules\BusNetwork\Http\Requests\UpdateStopRequest;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\DynamicPricing\Models\Fare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LineController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Line::class);

        $query = Line::query()
            ->withCount(['stops as stops_count' => fn ($q) => $q])
            ->withCount(['stops as active_stops_count' => fn ($q) => $q->where('status', 'active')])
            ->with(['stops' => fn ($q) => $q->orderBy('stop_order')])
            ->orderBy('code');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term]);
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, array_column(LineStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $type = $request->string('type')->trim()->value();
        if ($type !== '' && in_array($type, array_column(LineType::cases(), 'value'), true)) {
            $query->where('type', $type);
        }

        $lines = $query->paginate(15)->through(fn (Line $line): array => $this->serializeLine($line));

        return inertia('network', [
            'lines' => $lines,
            'statuses' => LineStatus::options(),
            'types' => LineType::options(),
            'permissions' => [
                'canCreate' => Gate::allows('create', Line::class),
                'canUpdate' => Gate::allows('update', new Line),
                'canDeactivate' => Gate::allows('deactivate', new Line),
                'canManageStops' => Gate::allows('create', Stop::class),
            ],
        ]);
    }

    public function show(Request $request, Line $line): Response
    {
        Gate::authorize('view', $line);

        $line->load([
            'stops' => fn ($q) => $q->orderBy('stop_order'),
            'fares' => fn ($q) => $q->where('status', FareStatus::Active->value)->with('serviceClass:id,uuid,code,name')->orderBy('base_amount'),
        ]);

        return inertia('network/detail', [
            'line' => $this->serializeLineDetail($line),
            'statuses' => LineStatus::options(),
            'timeline' => $this->buildTimeline($line),
            'permissions' => [
                'canUpdate' => Gate::allows('update', $line),
                'canDeactivate' => Gate::allows('deactivate', $line),
                'canManageStops' => Gate::allows('create', Stop::class),
                'canUpdateStop' => Gate::allows('update', new Stop),
                'canDeactivateStop' => Gate::allows('deactivate', new Stop),
                'canManageFares' => Gate::allows('create', Fare::class),
            ],
        ]);
    }

    public function store(StoreLineRequest $request, CreateLineAction $createLine): RedirectResponse
    {
        $createLine->execute($request->payload(), $request->stops());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ligne créée avec succès.')]);

        return to_route('network.index');
    }

    public function update(UpdateLineRequest $request, Line $line, UpdateLineAction $updateLine): RedirectResponse
    {
        $updateLine->execute($line, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ligne mise à jour avec succès.')]);

        return to_route('network.index');
    }

    public function changeStatus(Request $request, Line $line, ChangeLineStatusAction $changeLineStatus): RedirectResponse
    {
        Gate::authorize('deactivate', $line);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(LineStatus::cases(), 'value'))],
        ]);

        $changeLineStatus->execute($line, LineStatus::from($data['status']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut de la ligne mis à jour.')]);

        return to_route('network.index');
    }

    public function storeStop(StoreStopRequest $request, Line $line, CreateStopAction $createStop): RedirectResponse
    {
        $createStop->execute($line, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Arrêt ajouté avec succès.')]);

        return to_route('network.show', $line);
    }

    public function updateStop(UpdateStopRequest $request, Line $line, Stop $stop, UpdateStopAction $updateStop): RedirectResponse
    {
        $updateStop->execute($stop, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Arrêt mis à jour avec succès.')]);

        return to_route('network.show', $line);
    }

    public function deactivateStop(Line $line, Stop $stop, DeactivateStopAction $deactivateStop): RedirectResponse
    {
        Gate::authorize('deactivate', $stop);

        $deactivateStop->execute($stop);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Arrêt désactivé. Les billets déjà émis restent valides.')]);

        return to_route('network.show', $line);
    }

    /** @return array<string, mixed> */
    private function serializeLine(Line $line): array
    {
        /** @var Collection<int, Stop> $allStops */
        $allStops = $line->stops ?? collect();
        $activeStops = $allStops->filter(fn (Stop $s) => (string) $s->getRawOriginal('status') === 'active')->values();
        $firstStop = $activeStops->first();
        $lastStop = $activeStops->last();
        $activeCount = (int) ($line->active_stops_count ?? $activeStops->count());

        $itinerary = match (true) {
            $firstStop !== null && $lastStop !== null && $firstStop->name !== $lastStop->name => "{$firstStop->name} → {$lastStop->name}",
            $firstStop !== null => $firstStop->name,
            default => '—',
        };

        return [
            'uuid' => $line->uuid,
            'code' => $line->code,
            'name' => $line->name,
            'type' => (string) LineType::from((string) $line->getRawOriginal('type'))->value,
            'itinerary' => $itinerary,
            'stopsCount' => (int) ($line->stops_count ?? 0),
            'activeStopsCount' => $activeCount,
            'segmentsCount' => max(0, $activeCount - 1),
            'distanceKm' => $line->distance_km !== null ? (int) $line->distance_km : null,
            'estimatedDurationMinutes' => $line->estimated_duration_minutes !== null ? (int) $line->estimated_duration_minutes : null,
            'status' => (string) LineStatus::from((string) $line->getRawOriginal('status'))->value,
            'countriesCrossed' => $line->countries_crossed,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeLineDetail(Line $line): array
    {
        /** @var array<int, array{uuid: string, name: string, city: string, country: string, stopOrder: int, kmFromOrigin: float, latitude: float|null, longitude: float|null, status: string}> $stops */
        $stops = $line->stops->map(fn (Stop $stop): array => [
            'uuid' => $stop->uuid,
            'name' => $stop->name,
            'city' => $stop->city,
            'country' => $stop->country,
            'stopOrder' => (int) $stop->stop_order,
            'kmFromOrigin' => (float) $stop->km_from_origin,
            'latitude' => $stop->latitude !== null ? (float) $stop->latitude : null,
            'longitude' => $stop->longitude !== null ? (float) $stop->longitude : null,
            'status' => (string) $stop->getRawOriginal('status'),
        ])->values()->all();

        /** @var array<int, array{uuid: string, originName: string, destinationName: string, serviceClassCode: string, serviceClassName: string, baseAmount: int, currency: string, effectiveFrom: string|null}> $fares */
        $fares = $line->fares->map(fn (Fare $fare): array => [
            'uuid' => $fare->uuid,
            'originName' => $fare->originStop?->name ?? '—',
            'destinationName' => $fare->destinationStop?->name ?? '—',
            'serviceClassCode' => $fare->serviceClass?->code ?? '—',
            'serviceClassName' => $fare->serviceClass?->name ?? '—',
            'baseAmount' => (int) $fare->base_amount,
            'currency' => $fare->currency,
            'effectiveFrom' => $fare->effective_from?->toDateString(),
        ])->values()->all();

        return [
            'uuid' => $line->uuid,
            'code' => $line->code,
            'name' => $line->name,
            'type' => (string) LineType::from((string) $line->getRawOriginal('type'))->value,
            'distanceKm' => $line->distance_km !== null ? (int) $line->distance_km : null,
            'estimatedDurationMinutes' => $line->estimated_duration_minutes !== null ? (int) $line->estimated_duration_minutes : null,
            'status' => (string) LineStatus::from((string) $line->getRawOriginal('status'))->value,
            'countriesCrossed' => $line->countries_crossed,
            'activeStopsCount' => $line->stops->where(fn (Stop $s): bool => $s->getRawOriginal('status') === 'active')->count(),
            'stops' => $stops,
            'fares' => $fares,
            'createdAt' => $line->created_at?->toIso8601String(),
            'updatedAt' => $line->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Build a lightweight timeline of key line events.
     *
     * @return array<int, array{label: string, date: string|null}>
     */
    private function buildTimeline(Line $line): array
    {
        $events = [];

        if ($line->created_at) {
            $events[] = [
                'label' => 'Ligne créée',
                'date' => $line->created_at->toIso8601String(),
            ];
        }

        $lastStop = $line->stops->sortByDesc('created_at')->first();
        if ($lastStop && $lastStop->created_at) {
            $events[] = [
                'label' => 'Arrêts ordonnés',
                'date' => $lastStop->created_at->toIso8601String(),
            ];
        }

        if ($line->updated_at && $line->updated_at->notEqualTo($line->created_at)) {
            $events[] = [
                'label' => 'Ligne publiée',
                'date' => $line->updated_at->toIso8601String(),
            ];
        }

        return $events;
    }
}
