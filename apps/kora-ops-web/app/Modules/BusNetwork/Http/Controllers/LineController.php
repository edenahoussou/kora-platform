<?php

namespace App\Modules\BusNetwork\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Actions\ChangeLineStatusAction;
use App\Modules\BusNetwork\Actions\CreateLineAction;
use App\Modules\BusNetwork\Actions\SyncLineStopsAction;
use App\Modules\BusNetwork\Actions\UpdateLineAction;
use App\Modules\BusNetwork\Data\LineStatus;
use App\Modules\BusNetwork\Data\LineType;
use App\Modules\BusNetwork\Http\Requests\StoreLineRequest;
use App\Modules\BusNetwork\Http\Requests\UpdateLineRequest;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\DynamicPricing\Models\Fare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            ->with([
                'stops' => fn ($q) => $q->orderBy('stop_order'),
                'stops.physicalStop:id,uuid',
                'fares' => fn ($q) => $q->where('status', FareStatus::Active->value)->with([
                    'serviceClass:id,code,name',
                    'originStop:id,name',
                    'destinationStop:id,name',
                ])->orderBy('base_amount'),
            ])
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

        $lines = $query->get()->map(fn (Line $line): array => $this->serializeLine($line));

        return inertia('network', [
            'lines' => $lines,
            'physicalStops' => $this->serializePhysicalStops(),
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

    public function store(StoreLineRequest $request, CreateLineAction $createLine): RedirectResponse
    {
        $createLine->execute($request->payload(), $request->stops());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ligne créée avec succès.')]);

        return to_route('network.index');
    }

    public function update(UpdateLineRequest $request, Line $line, UpdateLineAction $updateLine, SyncLineStopsAction $syncStops): RedirectResponse
    {
        DB::transaction(function () use ($request, $line, $updateLine, $syncStops): void {
            $updateLine->execute($line, $request->payload());

            if ($request->has('stops')) {
                $syncStops->execute($line, $request->stops());
            }
        });

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

    /** @return array<int, array<string, mixed>> */
    private function serializePhysicalStops(): array
    {
        return PhysicalStop::query()
            ->where('status', 'active')
            ->whereHas('stops')
            ->orderBy('city')
            ->orderBy('name')
            ->get()
            ->map(fn (PhysicalStop $stop): array => [
                'uuid' => $stop->uuid,
                'code' => $stop->code,
                'name' => $stop->name,
                'city' => $stop->city,
                'country' => $stop->country,
                'latitude' => $stop->latitude !== null ? (float) $stop->latitude : null,
                'longitude' => $stop->longitude !== null ? (float) $stop->longitude : null,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function serializeLine(Line $line): array
    {
        /** @var Collection<int, Stop> $allStops */
        $allStops = $line->stops ?? collect();
        $activeStops = $allStops->filter(fn (Stop $s) => (string) $s->getRawOriginal('status') === 'active')->values();
        $firstStop = $activeStops->isNotEmpty() ? $activeStops->first() : null;
        $lastStop = $activeStops->isNotEmpty() ? $activeStops->last() : null;
        $activeCount = (int) ($line->active_stops_count ?? $activeStops->count());

        $itinerary = match (true) {
            $firstStop !== null && $lastStop !== null && $firstStop->city !== $lastStop->city => "{$firstStop->city} → {$lastStop->city}",
            $firstStop !== null => $firstStop->city,
            default => '—',
        };

        return [
            'uuid' => $line->uuid,
            'code' => $line->code,
            'name' => $line->name,
            'type' => (string) LineType::from((string) $line->getRawOriginal('type'))->value,
            'bidirectional' => (bool) $line->bidirectional,
            'itinerary' => $itinerary,
            'originCity' => $firstStop !== null ? $firstStop->city : '—',
            'destinationCity' => $lastStop !== null ? $lastStop->city : '—',
            'stopsCount' => (int) ($line->stops_count ?? 0),
            'activeStopsCount' => $activeCount,
            'segmentsCount' => max(0, $activeCount - 1),
            'distanceKm' => $line->distance_km !== null ? (int) $line->distance_km : null,
            'estimatedDurationMinutes' => $line->estimated_duration_minutes !== null ? (int) $line->estimated_duration_minutes : null,
            'status' => (string) LineStatus::from((string) $line->getRawOriginal('status'))->value,
            'countriesCrossed' => $line->countries_crossed ?? [],
            'stops' => $allStops->map(fn (Stop $stop): array => [
                'uuid' => $stop->uuid,
                'name' => $stop->name,
                'city' => $stop->city,
                'country' => $stop->country,
                'stopOrder' => (int) $stop->stop_order,
                'kmFromOrigin' => (float) $stop->km_from_origin,
                'latitude' => $stop->latitude !== null ? (float) $stop->latitude : null,
                'longitude' => $stop->longitude !== null ? (float) $stop->longitude : null,
                'status' => (string) $stop->getRawOriginal('status'),
                'physicalStopUuid' => $stop->physicalStop?->uuid,
            ])->values()->all(),
            'fares' => $line->fares->map(fn (Fare $fare): array => [
                'uuid' => $fare->uuid,
                'originName' => $fare->originStop->name,
                'destinationName' => $fare->destinationStop->name,
                'serviceClassName' => $fare->serviceClass->name,
                'baseAmount' => (int) $fare->base_amount,
                'currency' => $fare->currency,
            ])->values()->all(),
            'updatedAt' => $line->updated_at?->toIso8601String(),
        ];
    }
}
