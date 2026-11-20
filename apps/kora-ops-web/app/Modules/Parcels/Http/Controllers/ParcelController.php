<?php

namespace App\Modules\Parcels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Parcels\Http\Requests\ParcelIntakeRequest;
use App\Modules\Parcels\Http\Requests\ParcelOperationRequest;
use App\Modules\Parcels\Http\Requests\ParcelPaymentRequest;
use App\Modules\Parcels\Http\Resources\ParcelQuoteResource;
use App\Modules\Parcels\Http\Resources\ParcelResource;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Services\ParcelAccess;
use App\Modules\Parcels\Services\ParcelDocuments;
use App\Modules\Parcels\Services\ParcelIntake;
use App\Modules\Parcels\Services\ParcelNotifications;
use App\Modules\Parcels\Services\ParcelPayments;
use App\Modules\Parcels\Services\ParcelPricing;
use App\Modules\Parcels\Services\ParcelRouting;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @group KORA App Colis */
class ParcelController extends Controller
{
    public function __construct(private ParcelAccess $access, private ParcelPricing $pricing, private ParcelIntake $intake) {}

    /**
     * Lister les corridors Colis ouverts.
     *
     * @unauthenticated
     *
     * @response 200 {"data":{}}
     */
    public function corridors(ParcelRouting $routing): JsonResponse
    {
        $corridors = ParcelCorridor::query()->where('enabled', true)
            ->whereHas('originHub', fn ($query) => $query->where('enabled', true)->whereNull('archived_at'))
            ->whereHas('destinationHub', fn ($query) => $query->where('enabled', true)->whereNull('archived_at'))
            ->with(['originHub.stop', 'destinationHub.stop'])->get();

        $corridors = $routing->filterOperationallyAvailable($corridors);

        return response()->json(['data' => $corridors->map(fn (ParcelCorridor $corridor): array => [
            'uuid' => $corridor->uuid,
            'origin' => ['uuid' => $corridor->originHub?->uuid, 'city' => $corridor->originHub?->stop?->city, 'name' => $corridor->originHub?->stop?->name],
            'destination' => ['uuid' => $corridor->destinationHub?->uuid, 'city' => $corridor->destinationHub?->stop?->city, 'name' => $corridor->destinationHub?->stop?->name],
        ])]);
    }

    /**
     * Estimer le prix selon le poids déclaré.
     *
     * @unauthenticated
     *
     * @response 200 {"data":{"estimated":true,"weight_grams":5000,"total":1500,"net":1271,"tax":229,"currency":"XOF"}}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     * @response 422 {"message":"Les données de cette opération sont invalides."}
     */
    public function quote(ParcelIntakeRequest $request): JsonResponse
    {
        $corridor = ParcelCorridor::query()->where('uuid', $request->validated('corridor_uuid'))->firstOrFail();
        $this->pricing->ensureOpen($corridor);
        $quote = $this->pricing->calculate($corridor, $request->integer('weight_grams'));

        return (new ParcelQuoteResource($quote))->response();
    }

    /**
     * Pré-enregistrer un envoi.
     *
     * @authenticated
     *
     * @response 201 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function store(ParcelIntakeRequest $request): JsonResponse
    {
        $locations = $this->intake->resolve($request->validated());
        $parcel = $this->intake->createForDeposit($locations['deposit'], $locations['destination'], $request->user(), $request->validated(), false);

        return (new ParcelResource($parcel))->response()->setStatusCode($parcel->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Consulter ses envois et réceptions.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ParcelResource::collection($this->access->customer($request->user())->with(ParcelResource::relations())->latest('id')->paginate(25));
    }

    /**
     * Consulter un colis et ses jalons.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function show(Request $request, Parcel $parcel): ParcelResource
    {
        $parcel = $this->access->customer($request->user())->findOrFail($parcel->id);

        return new ParcelResource($parcel->load('events'));
    }

    /**
     * Annuler un pré-enregistrement.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function cancel(Request $request, Parcel $parcel): ParcelResource
    {
        $this->access->require($request->user(), 'parcels.customer');

        return new ParcelResource($this->intake->cancel($parcel, $request->user(), false));
    }

    /**
     * Suivre un colis par référence logistique.
     *
     * @unauthenticated
     *
     * @response 200 {"data":{"status":"in_transit","origin":"Cotonou","destination":"Parakou"}}
     */
    public function tracking(string $reference): JsonResponse
    {
        $parcel = Parcel::query()->where('reference', $reference)->firstOrFail();

        return $this->publicTracking($parcel);
    }

    /**
     * Suivre un colis par lien partagé.
     *
     * @unauthenticated
     *
     * @response 200 {"data":{"status":"in_transit","origin":"Cotonou","destination":"Parakou"}}
     */
    public function shared(string $token): JsonResponse
    {
        $parcel = Parcel::query()->where('share_hash', hash('sha256', $token))->firstOrFail();

        return $this->publicTracking($parcel);
    }

    /**
     * Créer ou remplacer le lien de suivi.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function share(Request $request, Parcel $parcel): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.customer');
        abort_unless($parcel->sender_user_id === $request->user()->id && $parcel->reference !== null, 404);
        $token = Str::random(48);
        DB::transaction(function () use ($parcel, $request, $token): void {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $parcel->update(['share_hash' => hash('sha256', $token)]);
            $parcel->record('tracking_link_issued', $request->user(), channel: 'customer');
        });

        return response()->json(['data' => ['path' => '/api/v1/parcels/shared/'.$token]]);
    }

    /**
     * Révoquer le lien de suivi.
     *
     * @authenticated
     *
     * @response 204
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function revokeShare(Request $request, Parcel $parcel): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.customer');
        abort_unless($parcel->sender_user_id === $request->user()->id, 404);
        DB::transaction(function () use ($parcel, $request): void {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $parcel->update(['share_hash' => null]);
            $parcel->record('tracking_link_revoked', $request->user(), channel: 'customer');
        });

        return response()->json(null, 204);
    }

    /**
     * Lister les moyens de paiement du corridor.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function methods(Request $request, Parcel $parcel, PaymentManager $manager): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.customer');
        abort_unless($parcel->sender_user_id === $request->user()->id, 404);
        $country = strtoupper((string) $parcel->corridor?->originHub?->agency?->country);
        $catalog = $manager->catalog();
        $methods = array_map(function (array $method) use ($country, $parcel): array {
            if ($method['code'] === 'wallet') {
                $method['capabilities']['cash'] = $method['capabilities']['cash'] && $parcel->corridor?->wallet_cash_enabled;
                $method['enabled'] = $method['capabilities']['cash'] || $method['capabilities']['points'];
                $method['label'] = 'Wallet KORA';
            } else {
                $method['providers'] = array_values(array_filter($method['providers'], fn (array $provider): bool => in_array($country, $provider['countries'], true)));
                $method['enabled'] = $method['providers'] !== [];
            }

            return $method;
        }, $catalog['methods']);

        return response()->json(['data' => ['country' => $country, 'currency' => 'XOF', 'methods' => $methods]]);
    }

    /**
     * Initier le paiement du devis pesé.
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
        return new PaymentResource($payments->pay($parcel, $request->user(), $request->validated(), false));
    }

    /**
     * Consulter une tentative de paiement Colis.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function payment(Request $request, Parcel $parcel, Payment $payment): PaymentResource
    {
        $this->assertPaymentOwner($request, $parcel, $payment);

        return new PaymentResource($payment);
    }

    /**
     * Vérifier la transaction auprès du prestataire.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function verify(ParcelPaymentRequest $request, Parcel $parcel, Payment $payment, ParcelPayments $payments, PaymentManager $manager): PaymentResource
    {
        $this->assertPaymentOwner($request, $parcel, $payment);

        return new PaymentResource($manager->supports($payment->provider)
            ? $payments->verify($payment, $request->validated('transaction_id'))
            : $payment);
    }

    /**
     * Récupérer le QR ou le reçu fiscal.
     *
     * @authenticated
     *
     * @response 200 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function documents(ParcelOperationRequest $request, Parcel $parcel, ParcelDocuments $documents): JsonResponse
    {
        $parcel = $this->access->customer($request->user())->findOrFail($parcel->id);
        $kind = $request->validated('kind') ?? 'label';
        if ($kind !== 'label') {
            abort_unless($parcel->sender_user_id === $request->user()->id, 404);
        }

        return response()->json(['data' => $documents->render($parcel, $kind)]);
    }

    /**
     * Signaler un problème sur un colis.
     *
     * @authenticated
     *
     * @response 201 {"data":{}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function incident(ParcelOperationRequest $request, Parcel $parcel, ParcelNotifications $notifications): JsonResponse
    {
        $parcel = $this->access->customer($request->user())->findOrFail($parcel->id);

        return DB::transaction(function () use ($request, $parcel, $notifications): JsonResponse {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            abort_if($parcel->reference === null, 409);
            $incident = $parcel->incidents()->create($request->validated() + ['blocking' => false]);
            $parcel->record('incident_reported', $request->user(), details: ['incident_uuid' => $incident->uuid], channel: 'customer');
            $notifications->incident($parcel, $incident->uuid);

            return response()->json(['data' => ['uuid' => $incident->uuid]], 201);
        });
    }

    private function assertPaymentOwner(Request $request, Parcel $parcel, Payment $payment): void
    {
        $this->access->require($request->user(), 'parcels.customer');
        abort_unless($parcel->sender_user_id === $request->user()->id
            && $parcel->charges()->whereKey($payment->parcel_charge_id)->exists(), 404);
    }

    private function publicTracking(Parcel $parcel): JsonResponse
    {
        return response()->json(['data' => [
            'status' => $parcel->incidents()->where('blocking', true)->whereNull('resolved_at')->exists() ? 'incident' : $parcel->status,
            'origin' => $parcel->corridor?->originHub?->stop?->city,
            'destination' => $parcel->corridor?->destinationHub?->stop?->city,
        ]])->header('Cache-Control', 'no-store');
    }
}
