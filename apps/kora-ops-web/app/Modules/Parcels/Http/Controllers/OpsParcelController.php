<?php

namespace App\Modules\Parcels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Data\CashLedgerKind;
use App\Modules\Cash\Data\CashLedgerStatus;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Modules\Fiscalization\Data\FiscalInvoiceDocumentKind;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Models\FiscalSyncAttempt;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Parcels\Http\Requests\ParcelConfigurationRequest;
use App\Modules\Parcels\Http\Requests\ParcelIntakeRequest;
use App\Modules\Parcels\Http\Requests\ParcelOperationRequest;
use App\Modules\Parcels\Http\Requests\ParcelPaymentRequest;
use App\Modules\Parcels\Http\Resources\ParcelQuoteResource;
use App\Modules\Parcels\Http\Resources\ParcelResource;
use App\Modules\Parcels\Jobs\FiscalizeParcelInvoiceJob;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelIncident;
use App\Modules\Parcels\Services\ParcelAccess;
use App\Modules\Parcels\Services\ParcelDocuments;
use App\Modules\Parcels\Services\ParcelFinance;
use App\Modules\Parcels\Services\ParcelHandoff;
use App\Modules\Parcels\Services\ParcelIntake;
use App\Modules\Parcels\Services\ParcelManifest;
use App\Modules\Parcels\Services\ParcelNotifications;
use App\Modules\Parcels\Services\ParcelPayments;
use App\Modules\Parcels\Services\ParcelPricing;
use App\Modules\Parcels\Services\ParcelReceipts;
use App\Modules\Parcels\Services\ParcelRouting;
use App\Modules\Parcels\Services\ParcelTransport;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Settings\Settings\GeneralSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/** @group KORA OPS Colis */
class OpsParcelController extends Controller
{
    public function __construct(
        private ParcelAccess $access,
        private ParcelTransport $transport,
        private ParcelHandoff $handoff,
        private ParcelManifest $manifest,
    ) {}

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
     * Preview the exact tariff breakdown before counter intake.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Parcel corridor is unavailable."}
     */
    public function quote(ParcelConfigurationRequest $request, ParcelPricing $pricing): ParcelQuoteResource
    {
        $this->access->require($request->user(), 'parcels.intake');
        $deposit = ParcelHub::query()->where('uuid', $request->validated('deposit_hub_uuid'))->firstOrFail();
        $destination = ParcelHub::query()->where('uuid', $request->validated('destination_hub_uuid'))->firstOrFail();
        $this->access->atHub($request->user(), $deposit, 'parcels.intake');

        $corridor = ParcelCorridor::query()
            ->where('origin_hub_id', $deposit->id)
            ->where('destination_hub_id', $destination->id)
            ->first();
        abort_if($corridor === null, 409, 'No parcel corridor connects this deposit location to the requested destination.');

        $pricing->ensureOpen($corridor);
        $quote = $pricing->calculate($corridor, $request->integer('weight_grams'));

        return new ParcelQuoteResource($quote);
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
     * Lister les moyens d’encaissement disponibles au guichet du hub de dépôt.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Le colis n’a pas de hub de dépôt exploitable."}
     */
    public function paymentOptions(Request $request, Parcel $parcel): JsonResponse
    {
        $parcel->loadMissing('corridor.originHub.agency');
        $hub = $parcel->corridor?->originHub;
        abort_if($hub === null, 409, 'The parcel has no deposit hub.');
        $this->access->atHub($request->user(), $hub, 'parcels.intake');
        $session = CashSession::query()
            ->where('agent_id', $request->user()->id)
            ->where('agency_id', $hub->agency_id)
            ->whereDate('business_date', now()->toDateString())
            ->where('status', CashSessionStatus::Open)
            ->with(['cashRegister', 'ledgers' => fn ($query) => $query
                ->where('status', CashLedgerStatus::Open)
                ->with('agencyPaymentAccount')])
            ->first();
        $ledgers = $session === null ? collect() : $session->ledgers;
        $cash = $ledgers->first(fn (CashLedger $ledger): bool => $ledger->kind === CashLedgerKind::Cash);
        $accounts = $ledgers
            ->filter(fn (CashLedger $ledger): bool => $ledger->kind === CashLedgerKind::MobileMoney
                && $ledger->agencyPaymentAccount?->isActive())
            ->map(fn (CashLedger $ledger): array => [
                'uuid' => $ledger->agencyPaymentAccount?->uuid,
                'network' => $ledger->agencyPaymentAccount?->network?->value,
                'label' => $ledger->agencyPaymentAccount?->label,
            ])->values();

        return response()->json(['data' => [
            'agency' => $hub->agency === null ? null : ['uuid' => $hub->agency->uuid, 'name' => $hub->agency->name],
            'hub' => ['uuid' => $hub->uuid, 'kind' => $hub->kind, 'name' => $hub->stop?->name],
            'currency' => app(GeneralSettings::class)->currency,
            'cash_session' => $session === null ? null : [
                'uuid' => $session->uuid,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'cash_register' => $session->cashRegister?->label,
            ],
            'methods' => [
                ['code' => 'cash', 'label' => 'Espèces', 'available' => $cash !== null],
                ['code' => 'mobile_money', 'label' => 'Mobile Money', 'available' => $accounts->isNotEmpty(), 'accounts' => $accounts],
            ],
        ]]);
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
     * Lister les départs Bus compatibles pour l’affectation d’un colis.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Le colis n’est pas prêt ou sa charge n’est pas réglée."}
     */
    public function assignmentOptions(Request $request, Parcel $parcel): JsonResponse
    {
        return response()->json(['data' => $this->transport->assignmentOptions($parcel, $request->user())]);
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
        $this->manifest->authorize($request->user(), $departure, $crew);

        return response()->json(['data' => $this->manifest->build($departure)]);
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
     * Consulter le reçu Colis et son état fiscal.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Le reçu n’est pas encore disponible."}
     */
    public function receipt(Request $request, Parcel $parcel, ParcelReceipts $receipts): JsonResponse
    {
        $this->access->operations($request->user())->findOrFail($parcel->id);
        $parcel->loadMissing('corridor.originHub');
        $hub = $parcel->corridor?->originHub;
        abort_if($hub === null, 409, 'The parcel has no deposit hub.');
        $this->access->atHub($request->user(), $hub, 'parcels.intake');
        $kind = $request->query('kind', 'receipt');
        abort_unless(in_array($kind, ['receipt', 'return_receipt'], true), 422);

        return response()->json(['data' => $receipts->build($parcel, (string) $kind)]);
    }

    /**
     * Relancer la fiscalisation d’une facture Colis en erreur.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour le rapprochement."}
     */
    public function retryFiscalization(Request $request, Parcel $parcel): JsonResponse
    {
        $this->access->operations($request->user())->findOrFail($parcel->id);
        $this->access->require($request->user(), 'parcels.finance');
        $invoices = FiscalInvoice::query()
            ->whereIn('parcel_charge_id', $parcel->charges()->pluck('id'))
            ->where('document_kind', FiscalInvoiceDocumentKind::Sale)
            ->whereIn('status', [FiscalInvoiceStatus::Pending, FiscalInvoiceStatus::Failed])
            ->get();
        $invoices->each(fn (FiscalInvoice $invoice) => FiscalizeParcelInvoiceJob::dispatch($invoice->id)->afterCommit());

        return response()->json(['data' => [
            'dispatched' => $invoices->count(),
            'invoices' => $invoices->map(fn (FiscalInvoice $invoice): array => [
                'uuid' => $invoice->uuid,
                'status' => $invoice->status->value,
            ])->values(),
        ]]);
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
        $invoices = FiscalInvoice::query()
            ->whereIn('parcel_charge_id', $parcel->charges()->pluck('id'))
            ->with(['payment', 'syncAttempts'])
            ->latest('id')->get();

        return response()->json(['data' => [
            'payments' => $payments->map(fn (Payment $payment): array => [
                'uuid' => $payment->uuid,
                'status' => $payment->status->value,
                'method' => $payment->method?->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider' => $payment->provider,
                'provider_reference' => $payment->provider_reference,
                'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
                'reconciliation_required' => $payment->failure_code === 'parcel_reconciliation_required',
                'refunds' => $payment->refunds->map(fn (PaymentRefund $refund): array => [
                    'uuid' => $refund->uuid, 'status' => $refund->status->value, 'amount' => $refund->amount,
                ]),
            ])->values(),
            'invoices' => $invoices->map(fn (FiscalInvoice $invoice): array => [
                'uuid' => $invoice->uuid,
                'payment_uuid' => $invoice->payment?->uuid,
                'reference' => $invoice->kora_reference,
                'status' => $invoice->status->value,
                'net' => $invoice->total_without_tax,
                'tax' => $invoice->total_tax,
                'total' => $invoice->total_with_tax,
                'fiscalized_at' => $invoice->fiscalized_at?->toIso8601String(),
            ])->values(),
            'attempts' => $invoices->flatMap(fn (FiscalInvoice $invoice) => $invoice->syncAttempts->map(fn (FiscalSyncAttempt $attempt): array => [
                'invoice_uuid' => $invoice->uuid,
                'operation' => $attempt->operation,
                'attempt_number' => $attempt->attempt_number,
                'status' => $attempt->status,
                'response_code' => $attempt->response_code,
                'error_code' => $attempt->error_code,
                'attempted_at' => $attempt->attempted_at->toIso8601String(),
                'completed_at' => $attempt->completed_at?->toIso8601String(),
            ]))->values(),
            'reconciliations' => $payments
                ->filter(fn (Payment $payment): bool => $payment->failure_code === 'parcel_reconciliation_required')
                ->map(fn (Payment $payment): array => [
                    'payment_uuid' => $payment->uuid,
                    'reason_code' => 'duplicate_or_late_confirmation',
                    'detected_at' => $payment->updated_at->toIso8601String(),
                ])->values(),
        ]]);
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
