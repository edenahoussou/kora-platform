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
}
