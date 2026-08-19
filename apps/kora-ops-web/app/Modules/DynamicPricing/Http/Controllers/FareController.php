<?php

namespace App\Modules\DynamicPricing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Actions\ChangeFareStatusAction;
use App\Modules\DynamicPricing\Actions\CreateFareAction;
use App\Modules\DynamicPricing\Actions\UpdateFareAction;
use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\DynamicPricing\Http\Requests\StoreFareRequest;
use App\Modules\DynamicPricing\Http\Requests\UpdateFareRequest;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FareController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Fare::class);

        $query = Fare::query()
            ->with(['line:id,uuid,code,name', 'originStop:id,uuid,name', 'destinationStop:id,uuid,name', 'serviceClass:id,uuid,code,name'])
            ->orderByDesc('effective_from')
            ->orderBy('line_id');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(currency) LIKE ?', [$term])
                    ->orWhereHas('line', fn ($line) => $line
                        ->whereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('originStop', fn ($stop) => $stop->whereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('destinationStop', fn ($stop) => $stop->whereRaw('LOWER(name) LIKE ?', [$term]));
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, array_column(FareStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $lineId = $request->string('line')->trim()->value();
        if ($lineId !== '') {
            $line = Line::query()->where('uuid', $lineId)->first();
            if ($line) {
                $query->where('line_id', $line->id);
            }
        }

        $serviceClassCode = $request->string('service')->trim()->value();
        if ($serviceClassCode !== '') {
            $serviceClass = ServiceClass::query()->where('code', $serviceClassCode)->first();
            if ($serviceClass) {
                $query->where('service_class_id', $serviceClass->id);
            }
        }

        $fares = $query->paginate(15)->through(fn (Fare $fare): array => $this->serializeFare($fare));

        return inertia('fares', [
            'fares' => $fares,
            'statuses' => FareStatus::options(),
            'serviceClasses' => ServiceClass::ordered()->get()->map(fn (ServiceClass $class): array => [
                'uuid' => $class->uuid,
                'code' => $class->code,
                'name' => $class->name,
            ]),
            'lines' => Line::query()->orderBy('code')->get()->map(fn (Line $line): array => [
                'uuid' => $line->uuid,
                'code' => $line->code,
                'name' => $line->name,
                'stops' => $line->stops()->orderBy('stop_order')->where('status', 'active')->get()->map(fn (Stop $stop): array => [
                    'uuid' => $stop->uuid,
                    'name' => $stop->name,
                    'stopOrder' => (int) $stop->stop_order,
                ]),
            ]),
            'permissions' => [
                'canCreate' => Gate::allows('create', Fare::class),
                'canUpdate' => Gate::allows('update', new Fare),
            ],
        ]);
    }

    public function show(Request $request, Fare $fare): Response
    {
        Gate::authorize('view', $fare);

        $fare->load([
            'line:id,uuid,code,name',
            'originStop:id,uuid,name,city,country',
            'destinationStop:id,uuid,name,city,country',
            'serviceClass:id,uuid,code,name',
        ]);

        // Versions : autres grilles sur le même segment (ligne + O/D + classe de service),
        // ordonnées par date d'effet pour visualiser l'historique tarifaire du segment.
        $versions = Fare::query()
            ->where('line_id', $fare->line_id)
            ->where('origin_stop_id', $fare->origin_stop_id)
            ->where('destination_stop_id', $fare->destination_stop_id)
            ->where('service_class_id', $fare->service_class_id)
            ->orderBy('effective_from')
            ->get()
            ->map(fn (Fare $version): array => $this->serializeVersion($version, $fare))
            ->values()
            ->all();

        return inertia('fares/detail', [
            'fare' => $this->serializeFareDetail($fare),
            'versions' => $versions,
            'timeline' => $this->buildTimeline($fare),
            'serviceClasses' => ServiceClass::ordered()->get()->map(fn (ServiceClass $class): array => [
                'uuid' => $class->uuid,
                'code' => $class->code,
                'name' => $class->name,
            ]),
            'lines' => Line::query()->orderBy('code')->get()->map(fn (Line $line): array => [
                'uuid' => $line->uuid,
                'code' => $line->code,
                'name' => $line->name,
                'stops' => $line->stops()->orderBy('stop_order')->where('status', 'active')->get()->map(fn (Stop $stop): array => [
                    'uuid' => $stop->uuid,
                    'name' => $stop->name,
                    'stopOrder' => (int) $stop->stop_order,
                ]),
            ]),
            'permissions' => [
                'canUpdate' => Gate::allows('update', $fare),
            ],
        ]);
    }

    public function store(StoreFareRequest $request, CreateFareAction $createFare): RedirectResponse
    {
        $createFare->execute($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tarif créé en brouillon. Validation explicite obligatoire.')]);

        return to_route('fares.index');
    }

    public function update(UpdateFareRequest $request, Fare $fare, UpdateFareAction $updateFare): RedirectResponse
    {
        $updateFare->execute($fare, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tarif mis à jour avec succès.')]);

        return to_route('fares.index');
    }

    public function changeStatus(Request $request, Fare $fare, ChangeFareStatusAction $changeFareStatus): RedirectResponse
    {
        Gate::authorize('update', $fare);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_column(FareStatus::cases(), 'value'))],
        ]);

        $changeFareStatus->execute($fare, FareStatus::from($data['status']));

        $message = match (FareStatus::from($data['status'])) {
            FareStatus::Active => __('Tarif activé. Il est désormais vendable.'),
            FareStatus::Archived => __('Tarif archivé. Les billets déjà émis restent valides.'),
            FareStatus::Draft => __('Tarif replacé en brouillon.'),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('fares.index');
    }

    /** @return array<string, mixed> */
    private function serializeFare(Fare $fare): array
    {
        return [
            'uuid' => $fare->uuid,
            'lineCode' => $fare->line?->code,
            'lineName' => $fare->line?->name,
            'originName' => $fare->originStop?->name,
            'destinationName' => $fare->destinationStop?->name,
            'serviceClassCode' => $fare->serviceClass?->code,
            'serviceClassName' => $fare->serviceClass?->name,
            'baseAmount' => (int) $fare->base_amount,
            'currency' => $fare->currency,
            'effectiveFrom' => $fare->effective_from?->toDateString(),
            'effectiveUntil' => $fare->effective_until?->toDateString(),
            'status' => FareStatus::from((string) $fare->getRawOriginal('status'))->value,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeFareDetail(Fare $fare): array
    {
        $status = FareStatus::from((string) $fare->getRawOriginal('status'));

        return [
            'uuid' => $fare->uuid,
            'reference' => $this->buildReference($fare),
            'lineUuid' => $fare->line?->uuid,
            'lineCode' => $fare->line?->code,
            'lineName' => $fare->line?->name,
            'originName' => $fare->originStop?->name,
            'originCity' => $fare->originStop?->city,
            'originCountry' => $fare->originStop?->country,
            'destinationName' => $fare->destinationStop?->name,
            'destinationCity' => $fare->destinationStop?->city,
            'destinationCountry' => $fare->destinationStop?->country,
            'serviceClassCode' => $fare->serviceClass?->code,
            'serviceClassName' => $fare->serviceClass?->name,
            'baseAmount' => (int) $fare->base_amount,
            'currency' => $fare->currency,
            'effectiveFrom' => $fare->effective_from?->toDateString(),
            'effectiveUntil' => $fare->effective_until?->toDateString(),
            'status' => $status->value,
            'createdAt' => $fare->created_at?->toIso8601String(),
            'updatedAt' => $fare->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Build a readable reference code: TAR-{lineCode}-{serviceCode}-{YYYY-MM}.
     */
    private function buildReference(Fare $fare): string
    {
        $lineCode = $fare->line?->code ?? '---';
        $serviceCode = $fare->serviceClass?->code ?? '---';
        $period = $fare->effective_from?->format('Y-m') ?? '----';

        return "TAR-{$lineCode}-{$serviceCode}-{$period}";
    }

    /**
     * Serialize a sibling fare as a version row for the Périodes tab.
     *
     * @return array<string, mixed>
     */
    private function serializeVersion(Fare $version, Fare $current): array
    {
        $status = FareStatus::from((string) $version->getRawOriginal('status'));

        return [
            'uuid' => $version->uuid,
            'reference' => $this->buildReference($version),
            'baseAmount' => (int) $version->base_amount,
            'currency' => $version->currency,
            'effectiveFrom' => $version->effective_from?->toDateString(),
            'effectiveUntil' => $version->effective_until?->toDateString(),
            'status' => $status->value,
            'isCurrent' => $version->is($current),
        ];
    }

    /**
     * Build a lightweight timeline of key fare events from the activity log,
     * with a graceful fallback to model timestamps when no activity is logged.
     *
     * @return array<int, array{label: string, date: string|null}>
     */
    private function buildTimeline(Fare $fare): array
    {
        $events = [];

        if ($fare->created_at) {
            $events[] = [
                'label' => 'Tarif créé',
                'date' => $fare->created_at->toIso8601String(),
            ];
        }

        if ($fare->effective_from && $fare->effective_from->isPast()) {
            $events[] = [
                'label' => 'Période contrôlée',
                'date' => $fare->effective_from->toIso8601String(),
            ];
        }

        $status = FareStatus::from((string) $fare->getRawOriginal('status'));
        if ($status === FareStatus::Active) {
            $events[] = [
                'label' => 'Grille approuvée',
                'date' => $fare->updated_at?->toIso8601String(),
            ];
        }

        if ($fare->updated_at && $fare->updated_at->notEqualTo($fare->created_at)) {
            $events[] = [
                'label' => 'Tarif mis à jour',
                'date' => $fare->updated_at->toIso8601String(),
            ];
        }

        return $events;
    }
}
