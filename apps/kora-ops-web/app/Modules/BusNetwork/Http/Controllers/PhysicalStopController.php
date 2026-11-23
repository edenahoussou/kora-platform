<?php

namespace App\Modules\BusNetwork\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Actions\FindOrCreatePhysicalStopAction;
use App\Modules\BusNetwork\Http\Requests\StorePhysicalStopRequest;
use App\Modules\BusNetwork\Http\Requests\UpdatePhysicalStopRequest;
use App\Modules\BusNetwork\Models\MapSetting;
use App\Modules\BusNetwork\Models\PhysicalStop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PhysicalStopController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PhysicalStop::class);

        $query = PhysicalStop::query()
            ->withCount(['stops as stops_count' => fn ($q) => $q->whereNull('deleted_at')])
            ->with(['stops.line:id,uuid,code,name'])
            ->orderBy('city')
            ->orderBy('name');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(city) LIKE ?', [$term]);
            });
        }

        $city = $request->string('city')->trim()->value();
        if ($city !== '') {
            $query->where('city', $city);
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '') {
            $query->where('status', $status);
        }

        $stops = $query->paginate(20)->withQueryString()->through(fn (PhysicalStop $stop): array => [
            'uuid' => $stop->uuid,
            'code' => $stop->code,
            'name' => $stop->name,
            'city' => $stop->city,
            'country' => $stop->country,
            'latitude' => $stop->latitude !== null ? (float) $stop->latitude : null,
            'longitude' => $stop->longitude !== null ? (float) $stop->longitude : null,
            'status' => (string) $stop->getRawOriginal('status'),
            'stopsCount' => (int) ($stop->stops_count ?? 0),
            'lines' => $stop->stops->map(fn ($s) => $s->line ? [
                'uuid' => $s->line->uuid,
                'code' => $s->line->code,
                'name' => $s->line->name,
            ] : null)->filter()->unique('uuid')->values()->all(),
            'updatedAt' => $stop->updated_at?->toIso8601String(),
        ]);

        $cities = PhysicalStop::query()->distinct()->orderBy('city')->pluck('city')->all();

        return inertia('stops/index', [
            'stops' => $stops,
            'cities' => $cities,
            'filters' => [
                'q' => $search,
                'city' => $city,
                'status' => $status,
            ],
            'mapSettings' => [
                'provider' => MapSetting::resolvedProvider()->value,
                'osmTileUrl' => (string) config('services.maps.openstreetmap.tile_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
                'osmAttribution' => (string) config('services.maps.openstreetmap.attribution', '© OpenStreetMap contributors'),
                'googleApiKey' => config('services.maps.google.api_key'),
            ],
            'permissions' => [
                'canCreate' => Gate::allows('create', PhysicalStop::class),
                'canUpdate' => Gate::allows('update', new PhysicalStop),
                'canDelete' => Gate::allows('delete', new PhysicalStop),
            ],
        ]);
    }

    public function store(StorePhysicalStopRequest $request, FindOrCreatePhysicalStopAction $action): RedirectResponse
    {
        $action->execute($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Point d’arrêt créé avec succès.')]);

        return to_route('stops.index');
    }

    public function update(UpdatePhysicalStopRequest $request, PhysicalStop $physicalStop): RedirectResponse
    {
        $physicalStop->update($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Point d’arrêt mis à jour avec succès.')]);

        return to_route('stops.index');
    }

    public function destroy(Request $request, PhysicalStop $physicalStop): RedirectResponse
    {
        Gate::authorize('delete', $physicalStop);

        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $attachedLines = $physicalStop->stops()
            ->whereHas('line', fn ($q) => $q->whereNull('deleted_at'))
            ->with('line:id,code,name')
            ->get()
            ->map(fn ($s) => $s->line ? "{$s->line->code} - {$s->line->name}" : null)
            ->filter()
            ->unique()
            ->values();

        if ($attachedLines->isNotEmpty()) {
            throw ValidationException::withMessages([
                'stop' => __('Impossible de supprimer ce point d’arrêt car il est rattaché aux lignes suivantes : :lines. Retirez-le d’abord de ces lignes.', [
                    'lines' => $attachedLines->implode(', '),
                ]),
            ]);
        }

        $physicalStop->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Point d’arrêt supprimé avec succès.')]);

        return to_route('stops.index');
    }
}
