<?php

namespace App\Modules\Parcels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Parcels\Http\Requests\ParcelIntakeRequest;
use App\Modules\Parcels\Http\Requests\ParcelOperationRequest;
use App\Modules\Parcels\Http\Requests\ParcelPaymentRequest;
use App\Modules\Parcels\Http\Resources\ParcelResource;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelAssignment;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelIncident;
use App\Modules\Parcels\Services\ParcelAccess;
use App\Modules\Parcels\Services\ParcelDocuments;
use App\Modules\Parcels\Services\ParcelFinance;
use App\Modules\Parcels\Services\ParcelHandoff;
use App\Modules\Parcels\Services\ParcelIntake;
use App\Modules\Parcels\Services\ParcelNotifications;
use App\Modules\Parcels\Services\ParcelPayments;
use App\Modules\Parcels\Services\ParcelRouting;
use App\Modules\Parcels\Services\ParcelTransport;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/** @group KORA OPS Colis */
class OpsParcelController extends Controller
{
    public function __construct(private ParcelAccess $access, private ParcelTransport $transport, private ParcelHandoff $handoff) {}

    /**
     * Lister et filtrer les colis du périmètre OPS.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function list(ParcelOperationRequest $request): AnonymousResourceCollection
    {
        $query = $this->access->operations($request->user());
        $data = $request->validated();
        $query->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->when(isset($data['reference']), fn ($query) => $query->where('reference', $data['reference']))
            ->when(isset($data['corridor_uuid']), fn ($query) => $query->whereRelation('corridor', 'uuid', $data['corridor_uuid']));

        return ParcelResource::collection($query->with(ParcelResource::relations())->latest('id')->paginate(25));
    }

    /**
     * Compter les colis par statut.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function indicators(Request $request): JsonResponse
    {
        $counts = $this->access->operations($request->user())->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return response()->json(['data' => $counts]);
    }

    /**
     * Consulter un dossier Colis.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function show(Request $request, Parcel $parcel): ParcelResource
    {
        return new ParcelResource($this->access->operations($request->user())->with('events')->findOrFail($parcel->id));
    }

    /**
     * Pré-enregistrer un colis au guichet.
     *
     * @authenticated
     *
     * @response 201 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function store(ParcelIntakeRequest $request, ParcelIntake $intake): JsonResponse
    {
        $locations = $intake->resolve($request->validated());
        $parcel = $intake->createForDeposit($locations['deposit'], $locations['destination'], $request->user(), $request->validated(), true);

        return (new ParcelResource($parcel))->response()->setStatusCode($parcel->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Lister les lieux de dépôt desservis pour une destination.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function depositLocations(Request $request, ParcelRouting $routing): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.intake');
        $data = $request->validate([
            'destination_hub_uuid' => ['required', 'uuid'],
        ]);

        $destination = ParcelHub::query()
            ->where('uuid', $data['destination_hub_uuid'])
            ->with('stop')
            ->firstOrFail();

        $corridors = ParcelCorridor::query()
            ->where('destination_hub_id', $destination->id)
            ->where('enabled', true)
            ->with(['originHub.stop', 'destinationHub.stop'])
            ->get();
        $available = $routing->filterOperationallyAvailable($corridors);

        $hubs = $available
            ->map(fn (ParcelCorridor $corridor): ?ParcelHub => $corridor->originHub)
            ->filter(fn (?ParcelHub $hub): bool => $hub !== null && $this->access->canViewHub($request->user(), $hub))
            ->unique('id')
            ->values()
            ->map(fn (ParcelHub $hub): array => [
                'uuid' => $hub->uuid,
                'kind' => $hub->kind,
                'name' => $hub->stop?->name,
                'city' => $hub->stop?->city,
                'agency_uuid' => $hub->agency?->uuid,
                'kora_point_uuid' => $hub->koraPoint?->uuid,
            ]);

        return response()->json(['data' => $hubs]);
    }

    /**
     * Peser le colis et confirmer le devis ou enregistrer un refus.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     * @response 422 {"message":"Les données de cette opération sont invalides."}
     */
    public function inspect(ParcelIntakeRequest $request, Parcel $parcel, ParcelIntake $intake): ParcelResource
    {
        return new ParcelResource($intake->inspect($parcel, $request->user(), $request->validated()));
    }

    /**
     * Annuler une demande au guichet.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function cancel(Request $request, Parcel $parcel, ParcelIntake $intake): ParcelResource
    {
        return new ParcelResource($intake->cancel($parcel, $request->user(), true));
    }

    /**
     * Encaisser un devis définitif au guichet.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Le montant doit correspondre au dernier devis pesé ou un paiement est en cours."}
     * @response 422 {"message":"Les données de cette opération sont invalides."}
     */
    public function pay(ParcelPaymentRequest $request, Parcel $parcel, ParcelPayments $payments): PaymentResource
    {
        return new PaymentResource($payments->pay($parcel, $request->user(), $request->validated(), true));
    }

    /**
     * Valider le colis au hub d’origine.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function ready(Request $request, Parcel $parcel): ParcelResource
    {
        return new ParcelResource($this->transport->ready($parcel, $request->user()));
    }

    /**
     * Affecter le colis à un départ Bus direct.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Capacité de soute insuffisante ou hubs incompatibles avec le départ."}
     */
    public function assign(ParcelOperationRequest $request, Parcel $parcel): ParcelResource
    {
        $departure = Departure::query()->where('uuid', $request->validated('departure_uuid'))->firstOrFail();

        return new ParcelResource($this->transport->assign($parcel, $departure, $request->user()));
    }

    /**
     * Corriger la garde par déchargement au hub d’origine.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function correction(ParcelOperationRequest $request, Parcel $parcel): ParcelResource
    {
        return new ParcelResource($this->transport->unloadAtOrigin($parcel, $request->user(), $request->validated('reason')));
    }

    /**
     * Télécharger le manifeste de soute versionné.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function manifest(Request $request, Departure $departure, CrewDepartureScope $crew): JsonResponse
    {
        $user = $request->user();
        abort_unless(($user->can('parcels.network') && $user->can('parcels.view'))
            || $crew->query($user, 'parcels.scan')->whereKey($departure->id)->exists(), 403);
        $rows = ParcelAssignment::query()->where('departure_id', $departure->id)->where('bus_id', $departure->bus_id)
            ->whereNull('released_at')->with(['parcel.corridor.originHub.stop', 'parcel.corridor.destinationHub.stop'])
            ->orderBy('id')->get()->map(static function (ParcelAssignment $assignment): array {
                $corridor = $assignment->parcel?->corridor;
                $origin = $assignment->return_leg ? $corridor?->destinationHub : $corridor?->originHub;
                $destination = $assignment->return_leg ? $corridor?->originHub : $corridor?->destinationHub;

                return [
                    'assignment_uuid' => $assignment->uuid,
                    'reference' => $assignment->parcel?->reference,
                    'weight_grams' => $assignment->parcel?->weight_grams,
                    'origin_stop_uuid' => $origin?->stop?->uuid,
                    'destination_stop_uuid' => $destination?->stop?->uuid,
                    'loaded_at' => $assignment->loaded_at?->toIso8601String(),
                    'unloaded_at' => $assignment->unloaded_at?->toIso8601String(),
                ];
            })->all();

        return response()->json(['data' => [
            'departure_uuid' => $departure->uuid, 'version' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'parcels' => $rows,
        ]]);
    }

    /**
     * Enregistrer un scan logistique en ligne.
     *
     * @authenticated
     *
     * @response 200 {"data":{"reference":"KC-20261112-12345","status":"in_transit"}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Le scan est incompatible avec le départ, l’arrêt ou l’état de garde."}
     * @response 422 {"message":"Les données de cette opération sont invalides."}
     */
    public function scan(ParcelOperationRequest $request, Departure $departure): JsonResponse
    {
        $terminal = $request->attributes->get('terminal');
        abort_unless($terminal instanceof TerminalP501, 403);
        $parcel = $this->transport->scan($departure, $request->user(), $terminal, $request->validated('reference'),
            $request->validated('stop_uuid'), $request->validated('direction') === 'load',
            CarbonImmutable::parse($request->validated('observed_at')), $request->validated('operation_uuid'));

        return response()->json(['data' => ['reference' => $parcel->reference, 'status' => $parcel->status]]);
    }

    /**
     * Confirmer la réception au hub et émettre le code de retrait.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function receive(Request $request, Parcel $parcel): ParcelResource
    {
        return new ParcelResource($this->handoff->receive($parcel, $request->user()));
    }

    /**
     * Révoquer et renouveler le code de retrait.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function reissue(Request $request, Parcel $parcel): ParcelResource
    {
        return new ParcelResource($this->handoff->reissue($parcel, $request->user()));
    }

    /**
     * Remettre le colis contre un code ou une vérification autorisée.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     * @response 422 {"message":"Les données de cette opération sont invalides."}
     * @response 429 {"message":"Nombre maximal d’essais de retrait atteint."}
     */
    public function release(ParcelOperationRequest $request, Parcel $parcel): ParcelResource
    {
        return new ParcelResource($this->handoff->release($parcel, $request->user(), $request->validated()));
    }

    /**
     * Préparer l’encaissement du retour.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function returnCharge(Request $request, Parcel $parcel): ParcelResource
    {
        $this->handoff->prepareReturnCharge($parcel, $request->user());

        return new ParcelResource($parcel->refresh());
    }

    /**
     * Récupérer les données ESC/POS de l’étiquette ou du reçu.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function documents(ParcelOperationRequest $request, Parcel $parcel, ParcelDocuments $documents): JsonResponse
    {
        $this->access->operations($request->user())->findOrFail($parcel->id);
        $kind = $request->validated('kind') ?? 'label';
        $hub = $kind === 'label' ? ($parcel->custodyHub ?? $parcel->corridor->originHub) : $parcel->corridor->originHub;
        $this->access->atHub($request->user(), $hub, 'parcels.intake');
        $terminal = $request->attributes->get('terminal');

        return response()->json(['data' => $documents->render($parcel, $kind, $request->user(), $terminal instanceof TerminalP501 ? $terminal : null)]);
    }

    /**
     * Journaliser le résultat d’une impression sur le P501.
     *
     * @authenticated
     *
     * @response 204
     * @response 409 {"message":"Le résultat de l’impression a déjà été enregistré."}
     */
    public function printResult(ParcelOperationRequest $request, Parcel $parcel, ParcelDocuments $documents): JsonResponse
    {
        $this->access->operations($request->user())->findOrFail($parcel->id);
        $terminal = $request->attributes->get('terminal');
        abort_unless($terminal instanceof TerminalP501, 403);
        $documents->recordResult($parcel, $terminal, $request->user(), $request->validated('print_job_uuid'), $request->validated('outcome'));

        return response()->json(null, 204);
    }

    /**
     * Ouvrir un dossier d’incident bloquant.
     *
     * @authenticated
     *
     * @response 201 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function incident(ParcelOperationRequest $request, Parcel $parcel, ParcelNotifications $notifications): JsonResponse
    {
        $this->access->operations($request->user())->findOrFail($parcel->id);
        abort_unless($request->user()->can('parcels.intake') || $request->user()->can('parcels.assign'), 403);

        return DB::transaction(function () use ($request, $parcel, $notifications): JsonResponse {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            abort_if($parcel->reference === null || $parcel->status === 'withdrawn' || $parcel->return_handed_over_at !== null, 409);
            $incident = $parcel->incidents()->create($request->validated() + ['blocking' => true]);
            $parcel->record('incident_opened', $request->user(), $parcel->custodyHub, details: ['incident_uuid' => $incident->uuid]);
            $notifications->incident($parcel, $incident->uuid);

            return response()->json(['data' => ['uuid' => $incident->uuid]], 201);
        });
    }

    /**
     * Résoudre le dossier d’incident.
     *
     * @authenticated
     *
     * @response 204
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function resolveIncident(ParcelOperationRequest $request, Parcel $parcel, ParcelIncident $incident): JsonResponse
    {
        $this->access->operations($request->user())->findOrFail($parcel->id);
        $this->access->require($request->user(), 'parcels.override');

        DB::transaction(function () use ($request, $parcel, $incident): void {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $incident = $parcel->incidents()->whereNull('resolved_at')->findOrFail($incident->id);
            $incident->update(['resolved_at' => now()]);
            $parcel->record('incident_resolved', $request->user(), $parcel->custodyHub, details: [
                'incident_uuid' => $incident->uuid, 'reason' => $request->validated('reason'),
            ]);
        });

        return response()->json(null, 204);
    }

    /**
     * Consulter les encaissements et les rapprochements d’un dossier Colis.
     *
     * @authenticated
     *
     * @response 200 {"data":[]}
     * @response 403 {"message":"Droits insuffisants pour le rapprochement."}
     */
    public function payments(Request $request, Parcel $parcel): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.finance');
        $payments = $parcel->payments()->with('refunds')->latest('payments.id')->get();

        return response()->json(['data' => $payments->map(fn (Payment $payment): array => [
            'uuid' => $payment->uuid,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'provider_amount' => $payment->provider_amount,
            'provider' => $payment->provider,
            'provider_reference' => $payment->provider_reference,
            'reconciliation_required' => $payment->failure_code === 'parcel_reconciliation_required',
            'refunds' => $payment->refunds->map(fn (PaymentRefund $refund): array => [
                'uuid' => $refund->uuid, 'status' => $refund->status->value, 'amount' => $refund->amount,
            ]),
        ])]);
    }

    /**
     * Autoriser une restitution Colis.
     *
     * @authenticated
     *
     * @response 201 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function authorizeRefund(ParcelOperationRequest $request, Parcel $parcel, ParcelFinance $finance): JsonResponse
    {
        $payment = Payment::query()->where('uuid', $request->validated('payment_uuid'))->firstOrFail();
        $refund = $finance->authorizeRefund($parcel, $payment, $request->user(), $request->integer('amount'),
            $request->validated('reason'), $request->validated('idempotency_key'));

        return response()->json(['data' => ['uuid' => $refund->uuid, 'status' => $refund->status->value]], 201);
    }

    /**
     * Enregistrer l’exécution de la restitution.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function completeRefund(ParcelOperationRequest $request, Parcel $parcel, PaymentRefund $refund, ParcelFinance $finance): JsonResponse
    {
        $refund = $finance->completeRefund($parcel, $refund, $request->user(), $request->validated('proof'), $request->validated('cash_ledger_uuid'));

        return response()->json(['data' => ['uuid' => $refund->uuid, 'status' => $refund->status->value]]);
    }
}
