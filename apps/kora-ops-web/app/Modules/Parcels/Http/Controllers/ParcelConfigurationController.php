<?php

namespace App\Modules\Parcels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\Parcels\Http\Requests\ParcelConfigurationRequest;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Services\ParcelAccess;
use App\Modules\Parcels\Services\ParcelPricing;
use App\Modules\Parcels\Services\ParcelRouting;
use App\Modules\Parcels\Services\ParcelTransport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** @group KORA OPS Paramètres Colis */
class ParcelConfigurationController extends Controller
{
    public function __construct(private ParcelAccess $access) {}

    /**
     * Consulter les hubs, corridors et tarifs.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function index(Request $request): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.network');
        $hubs = ParcelHub::query()->with(['agency', 'koraPoint', 'stop'])->get()->map(fn (ParcelHub $hub): array => [
            'uuid' => $hub->uuid, 'enabled' => $hub->enabled, 'kind' => $hub->kind,
            'agency_uuid' => $hub->agency?->uuid, 'kora_point_uuid' => $hub->koraPoint?->uuid, 'stop_uuid' => $hub->stop?->uuid,
            'name' => $hub->agency?->name, 'city' => $hub->stop?->city,
            'archived_at' => $hub->archived_at?->toIso8601String(),
        ]);
        $corridors = ParcelCorridor::query()->with(['originHub', 'destinationHub', 'tariffs'])->get()->map(fn (ParcelCorridor $corridor): array => [
            'uuid' => $corridor->uuid, 'origin_hub_uuid' => $corridor->originHub?->uuid, 'destination_hub_uuid' => $corridor->destinationHub?->uuid,
            ...$corridor->only(['enabled', 'wallet_cash_enabled']),
            'tariffs' => $corridor->tariffs->sortByDesc('id')->values()->map(fn ($tariff) => $tariff->only([
                'uuid', 'base_amount', 'step_amount', 'step_grams', 'maximum_grams', 'return_amount', 'created_at',
            ])),
        ]);

        return response()->json(['data' => ['hubs' => $hubs, 'corridors' => $corridors]]);
    }

    /**
     * Configurer un hub de dépôt et retrait.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function hub(ParcelConfigurationRequest $request): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.configure');
        $stop = Stop::query()->where('uuid', $request->validated('stop_uuid'))->firstOrFail();

        if ($request->filled('kora_point_uuid')) {
            $point = KoraPoint::query()->where('uuid', $request->validated('kora_point_uuid'))->with('agency')->firstOrFail();
            $agency = $point->agency;
            abort_unless($agency !== null && $agency->kind === 'partner' && $agency->status === AgencyStatus::Active, 422, 'Partner point is not bound to an active partner agency.');
            abort_unless($point->status === 'active', 422, 'Partner point is not active.');
            $kind = ParcelHub::KIND_PARTNER_POINT;
            $pointId = $point->id;
        } else {
            $agency = Agency::query()->where('uuid', $request->validated('agency_uuid'))->where('kind', 'internal')->firstOrFail();
            $kind = ParcelHub::KIND_AGENCY;
            $pointId = null;
        }

        abort_unless(strtoupper((string) $agency->country) === strtoupper((string) $stop->country), 422, 'Le hub et son arrêt doivent être dans le même pays.');
        abort_if($pointId !== null && ParcelHub::query()->where('kora_point_id', $pointId)->where('stop_id', '!=', $stop->id)->exists(), 422, 'Partner point is already attached to another deposit location.');

        return DB::transaction(function () use ($request, $agency, $stop, $kind, $pointId): JsonResponse {
            $hub = ParcelHub::query()->updateOrCreate(
                ['agency_id' => $agency->id, 'stop_id' => $stop->id],
                ['kora_point_id' => $pointId, 'kind' => $kind, 'enabled' => $request->boolean('enabled'), 'archived_at' => null],
            );
            activity('parcels')->performedOn($hub)->causedBy($request->user())->withProperties($request->validated())->log('parcel_hub_configured');

            return response()->json(['data' => [
                'uuid' => $hub->uuid, 'kind' => $hub->kind, 'enabled' => $hub->enabled,
                'kora_point_uuid' => $hub->koraPoint?->uuid, 'archived_at' => $hub->archived_at?->toIso8601String(),
            ]]);
        });
    }

    /**
     * Archive a deposit location without deleting its history.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Deposit location is already archived."}
     */
    public function archive(Request $request, ParcelHub $hub): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.configure');

        return DB::transaction(function () use ($request, $hub): JsonResponse {
            $hub = ParcelHub::query()->lockForUpdate()->findOrFail($hub->id);
            abort_if($hub->isArchived(), 409, 'Deposit location is already archived.');
            $hub->update(['enabled' => false, 'archived_at' => now()]);
            activity('parcels')->performedOn($hub)->causedBy($request->user())->log('parcel_hub_archived');

            return response()->json(['data' => [
                'uuid' => $hub->uuid, 'enabled' => $hub->enabled, 'archived_at' => $hub->archived_at?->toIso8601String(),
            ]]);
        });
    }

    /**
     * List the departures that can actually carry a parcel from one hub to another.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Corridor is not configured or enabled."}
     */
    public function routing(ParcelConfigurationRequest $request, ParcelRouting $routing): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.view');
        $origin = ParcelHub::query()->where('uuid', $request->validated('origin_hub_uuid'))->with(['agency', 'stop'])->firstOrFail();
        $destination = ParcelHub::query()->where('uuid', $request->validated('destination_hub_uuid'))->with(['agency', 'stop'])->firstOrFail();
        abort_unless($this->access->canViewHub($request->user(), $origin) || $this->access->canViewHub($request->user(), $destination), 403);

        $corridor = ParcelCorridor::query()
            ->where('origin_hub_id', $origin->id)->where('destination_hub_id', $destination->id)->first();
        abort_if($corridor === null || ! $corridor->enabled || ! $routing->isActiveHub($origin) || ! $routing->isActiveHub($destination), 409, 'Corridor is not configured or enabled.');

        $availability = $routing->availability($origin, $destination);

        return response()->json(['data' => [
            'origin_hub_uuid' => $origin->uuid,
            'destination_hub_uuid' => $destination->uuid,
            'horizon_days' => $routing->horizonDays(),
            'departures' => $availability->map(fn (array $row): array => [
                'uuid' => $row['departure']->uuid,
                'line_code' => $row['departure']->line?->code,
                'departure_at' => $row['departure']->scheduledDepartureAt()->toIso8601String(),
                'status' => $row['departure']->status,
                'capacity_grams' => $row['capacity_grams'],
                'reserved_grams' => $row['reserved_grams'],
                'available_grams' => $row['capacity_grams'] === null ? null : max(0, $row['capacity_grams'] - $row['reserved_grams']),
            ]),
        ]]);
    }

    /**
     * Configurer et activer un corridor Colis.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function corridor(ParcelConfigurationRequest $request, ParcelPricing $pricing, ParcelRouting $routing): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.configure');
        $origin = ParcelHub::query()->where('uuid', $request->validated('origin_hub_uuid'))->with('stop')->firstOrFail();
        $destination = ParcelHub::query()->where('uuid', $request->validated('destination_hub_uuid'))->with('stop')->firstOrFail();
        abort_unless($origin->stop !== null && $destination->stop !== null
            && ($origin->stop->city !== $destination->stop->city || $origin->stop->country !== $destination->stop->country), 422);

        return DB::transaction(function () use ($request, $origin, $destination, $pricing, $routing): JsonResponse {
            $keys = ['origin_hub_id' => $origin->id, 'destination_hub_id' => $destination->id];
            $corridor = ParcelCorridor::query()->where($keys)->lockForUpdate()->first() ?? new ParcelCorridor($keys);
            $corridor->fill(Arr::except($request->validated(), ['origin_hub_uuid', 'destination_hub_uuid']));
            if ($corridor->enabled) {
                $routing->assertStructurallyRoutable($origin, $destination);
                abort_unless($corridor->exists, 409, 'Créez le corridor et publiez son tarif avant activation.');
                $pricing->ensureOpen($corridor);
                abort_unless(strtoupper((string) $origin->agency?->country) === 'BJ', 409, 'Le fournisseur fiscal configuré couvre les encaissements au Bénin.');
                $pricing->calculate($corridor, 1);
            }
            $corridor->save();
            activity('parcels')->performedOn($corridor)->causedBy($request->user())->withProperties($request->validated())->log('parcel_corridor_configured');

            return response()->json(['data' => ['uuid' => $corridor->uuid, 'enabled' => $corridor->enabled, 'routing' => $routing->isStructurallyRoutable($origin, $destination)]]);
        });
    }

    /**
     * Publier une version de tarif.
     *
     * @authenticated
     *
     * @response 201 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function tariff(ParcelConfigurationRequest $request, ParcelCorridor $corridor): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.tariffs');

        return DB::transaction(function () use ($request, $corridor): JsonResponse {
            $corridor = ParcelCorridor::query()->lockForUpdate()->findOrFail($corridor->id);
            $tariff = $corridor->tariffs()->create($request->validated() + ['created_by' => $request->user()->id]);
            activity('parcels')->performedOn($tariff)->causedBy($request->user())->withProperties($request->validated())->log('parcel_tariff_published');

            return response()->json(['data' => ['uuid' => $tariff->uuid]], 201);
        });
    }

    /**
     * Configurer la capacité Colis du départ.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function capacity(ParcelConfigurationRequest $request, Departure $departure, ParcelTransport $transport): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.configure');
        $budget = $transport->capacity($departure, $request->integer('capacity_grams'));
        activity('parcels')->performedOn($budget)->causedBy($request->user())->withProperties($request->validated())->log('parcel_capacity_configured');

        return response()->json(['data' => ['departure_uuid' => $departure->uuid, 'capacity_grams' => $budget->capacity_grams]]);
    }
}
