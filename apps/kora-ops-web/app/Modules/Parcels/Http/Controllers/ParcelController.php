<?php

namespace App\Modules\Parcels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Parcels\Http\Requests\ParcelIntakeRequest;
use App\Modules\Parcels\Http\Requests\ParcelOperationRequest;
use App\Modules\Parcels\Http\Requests\ParcelPaymentRequest;
use App\Modules\Parcels\Http\Resources\ParcelPublicTrackingResource;
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
use App\Modules\Settings\Settings\GeneralSettings;
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
     * Retourne uniquement les corridors activés, dont les hubs de départ et d’arrivée sont actifs et non archivés.
     * Les hubs sont filtrés par disponibilité opérationnelle. Aucun identifiant interne ni donnée personnelle n’est exposé.
     *
     * @unauthenticated
     *
     * @response 200 {"data":[{"uuid":"00000000-0000-4000-8000-000000000001","origin":{"uuid":"00000000-0000-4000-8000-000000000002","city":"Cotonou","name":"Cotonou · Dantokpa"},"destination":{"uuid":"00000000-0000-4000-8000-000000000003","city":"Parakou","name":"Parakou · Gare centrale"}}]}
     * @response 429 {"message":"Too Many Attempts."}
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
     * Estime le prix TTC à partir du corridor et du poids déclaré, avant dépôt.
     * Le calcul est autoritatif côté serveur : le client ne doit jamais envoyer un montant.
     *
     * @unauthenticated
     *
     * @response 200 {"data":{"estimated":true,"weight_grams":5000,"total":1500,"net":1271,"tax":229,"currency":"XOF"}}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     * @response 422 {"message":"Les données de cette opération sont invalides."}
     * @response 429 {"message":"Too Many Attempts."}
     */
    public function quote(ParcelIntakeRequest $request): JsonResponse
    {
        $corridor = ParcelCorridor::query()->where('uuid', $request->validated('corridor_uuid'))->firstOrFail();
        $this->pricing->ensureOpen($corridor);
        $quote = $this->pricing->calculate($corridor, $request->integer('weight_grams'));

        return (new ParcelQuoteResource($quote))->response();
    }

    /**
     * Pré-enregistre un envoi au guichet (dépôt direct uniquement).
     * `idempotency_key` est obligatoire : rejouer la même clé avec le même contenu renvoie le même colis (200), sans doublon.
     * La référence logistique et le code de retrait ne sont pas exposés avant inspection et paiement.
     *
     * @authenticated
     *
     * @response 201 {"data":{"uuid":"00000000-0000-4000-8000-000000000010","reference":null,"tracking_url":null,"qr_payload":null,"status":"preregistered","origin":"Cotonou","destination":"Parakou","recipient_name":"Destinataire privé","weight_grams":null,"declared_grams":5000,"inspection_state":"pending_inspection","can_pay":false,"expires_at":"2026-11-22T10:00:00+01:00","timeline":[]}}
     * @response 200 {"data":{"uuid":"00000000-0000-4000-8000-000000000010","reference":null,"status":"preregistered","origin":"Cotonou","destination":"Parakou","declared_grams":5000,"inspection_state":"pending_inspection","can_pay":false,"timeline":[]}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     * @response 422 {"message":"Les données de cette opération sont invalides."}
     * @response 429 {"message":"Too Many Attempts."}
     */
    public function store(ParcelIntakeRequest $request): JsonResponse
    {
        $locations = $this->intake->resolve($request->validated());
        $parcel = $this->intake->createForDeposit($locations['deposit'], $locations['destination'], $request->user(), $request->validated(), false);

        return (new ParcelResource($parcel))->response()->setStatusCode($parcel->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Liste paginée (25 par page) des colis où l’utilisateur est expéditeur ou destinataire vérifié.
     * Les données personnelles du destinataire ne sont visibles qu’après vérification du téléphone actuel par OTP consommé.
     * Seuls des UUID et références publiques sont exposés.
     *
     * @authenticated
     *
     * @response 200 {"data":[{"uuid":"00000000-0000-4000-8000-000000000010","reference":"KC-20261119-95262","tracking_url":"https://staging.koraexpress.app/api/v1/parcels/tracking/KC-20261119-95262","status":"registered","origin":"Cotonou","destination":"Parakou","recipient_name":"Destinataire privé","weight_grams":5000,"can_pay":false,"can_receipt":true}],"links":{"first":"https://staging.koraexpress.app/api/v1/parcels?page=1","last":null,"prev":null,"next":null},"meta":{"current_page":1,"per_page":25,"total":1}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ParcelResource::collection($this->access->customer($request->user())->with(ParcelResource::relations())->latest('id')->paginate(25));
    }

    /**
     * Détail d’un colis du titulaire ou d’un destinataire vérifié.
     * Retourne les jalons, le devis, les flags d’action et l’état de remise. Le code de retrait et les hashes ne sont jamais retournés.
     *
     * @authenticated
     *
     * @urlParam uuid string required UUID public du colis. Example: 00000000-0000-4000-8000-000000000010
     *
     * @response 200 {"data":{"uuid":"00000000-0000-4000-8000-000000000010","reference":"KC-20261119-95262","status":"registered","origin":"Cotonou","destination":"Parakou","recipient_name":"Destinataire privé","weight_grams":5000,"can_pay":false,"can_receipt":true,"withdraw_by":null,"eta":null,"timeline":[{"kind":"registered","observed_at":"2026-11-20T09:15:00+01:00"}]}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 404 {"message":"Not Found"}
     */
    public function show(Request $request, Parcel $parcel): ParcelResource
    {
        $parcel = $this->access->customer($request->user())->with(ParcelResource::relations())->findOrFail($parcel->id);

        return new ParcelResource($parcel);
    }

    /**
     * Annule un colis encore au statut pré-enregistré, avant inspection ou paiement.
     * Un colis refusé, payé, affecté ou remis ne peut jamais être annulé par cette opération.
     *
     * @authenticated
     *
     * @urlParam parcel_uuid string required UUID public du colis. Example: 00000000-0000-4000-8000-000000000010
     *
     * @response 200 {"data":{"uuid":"00000000-0000-4000-8000-000000000010","reference":null,"status":"cancelled","origin":"Cotonou","destination":"Parakou","can_pay":false,"timeline":[{"kind":"cancelled","observed_at":"2026-11-20T09:20:00+01:00"}]}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 404 {"message":"Not Found"}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function cancel(Request $request, Parcel $parcel): ParcelResource
    {
        $this->access->require($request->user(), 'parcels.customer');

        return new ParcelResource($this->intake->cancel($parcel, $request->user(), false)->load(ParcelResource::relations()));
    }

    /**
     * Suivre un colis par sa référence logistique publique.
     *
     * Contrat public minimal : référence, statut, trajet, poids, échéance de retrait, ETA et jalons publics.
     * Ne retourne jamais le nom, le téléphone, le contenu, le code de retrait, les hashes, les UUID internes
     * ou le payload d’un prestataire. Le statut `incident` signale un incident bloquant non résolu, sans détail.
     *
     * @unauthenticated
     *
     * @urlParam reference string required Référence logistique publique. Example: KC-20261119-95262
     *
     * @response 200 {"data":{"reference":"KC-20261119-95262","status":"in_transit","origin":"Cotonou","destination":"Parakou","weight_grams":5000,"withdraw_by":null,"eta":"2026-11-20T18:30:00+01:00","last_event_at":"2026-11-20T14:05:00+01:00","timeline":[{"kind":"registered","observed_at":"2026-11-19T10:15:00+01:00","city":"Cotonou"},{"kind":"assigned","observed_at":"2026-11-20T08:00:00+01:00","city":"Cotonou"},{"kind":"in_transit","observed_at":"2026-11-20T14:05:00+01:00","city":"Parakou"}]}}
     * @response 404 {"message":"Not Found"}
     * @response 429 {"message":"Too Many Attempts."}
     */
    public function tracking(string $reference): JsonResponse
    {
        $parcel = Parcel::query()->where('reference', $reference)
            ->with(ParcelPublicTrackingResource::relations())->firstOrFail();

        return (new ParcelPublicTrackingResource($parcel))->response()->header('Cache-Control', 'no-store');
    }

    /**
     * Suivre un colis via un lien partagé révocable.
     *
     * Le jeton est aléatoire (48 caractères) et seul son hash SHA-256 est stocké. La révocation invalide immédiatement
     * l’ancien lien (404). Le payload est strictement identique au suivi public par référence, sans PII.
     *
     * @unauthenticated
     *
     * @urlParam token string required Jeton de partage opaque de 48 caractères. Example: AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFGHIJKL
     *
     * @response 200 {"data":{"reference":"KC-20261119-95262","status":"available","origin":"Cotonou","destination":"Parakou","weight_grams":5000,"withdraw_by":"2026-12-19T10:00:00+01:00","eta":"2026-11-20T18:30:00+01:00","last_event_at":"2026-11-20T18:10:00+01:00","timeline":[{"kind":"registered","observed_at":"2026-11-19T10:15:00+01:00","city":"Cotonou"},{"kind":"available","observed_at":"2026-11-20T18:10:00+01:00","city":"Parakou"}]}}
     * @response 404 {"message":"Not Found"}
     * @response 429 {"message":"Too Many Attempts."}
     */
    public function shared(string $token): JsonResponse
    {
        $parcel = Parcel::query()->where('share_hash', hash('sha256', $token))
            ->with(ParcelPublicTrackingResource::relations())->firstOrFail();

        return (new ParcelPublicTrackingResource($parcel))->response()->header('Cache-Control', 'no-store');
    }

    /**
     * Crée ou remplace le lien de suivi révocable d’un colis payé et référencé.
     * Réservé à l’expéditeur propriétaire. Chaque appel émet un nouveau jeton et invalide l’ancien.
     * Retourne un chemin relatif ; le jeton complet n’est ni journalisé ni stocké en clair.
     *
     * @authenticated
     *
     * @urlParam parcel_uuid string required UUID public du colis. Example: 00000000-0000-4000-8000-000000000010
     *
     * @response 200 {"data":{"path":"/api/v1/parcels/shared/AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFGHIJKL"}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 404 {"message":"Not Found"}
     */
    public function share(Request $request, Parcel $parcel): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.customer');
        abort_unless($parcel->sender_user_id === $request->user()->id && $parcel->reference !== null, 404, 'Not Found');
        $token = Str::random(48);
        DB::transaction(function () use ($parcel, $request, $token): void {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $parcel->update(['share_hash' => hash('sha256', $token)]);
            $parcel->record('tracking_link_issued', $request->user(), channel: 'customer');
        });

        return response()->json(['data' => ['path' => '/api/v1/parcels/shared/'.$token]]);
    }

    /**
     * Révoque immédiatement le lien de suivi partagé.
     * Réservé à l’expéditeur propriétaire. Le jeton précédent renvoie ensuite 404, sans révéler l’existence du colis.
     *
     * @authenticated
     *
     * @urlParam parcel_uuid string required UUID public du colis. Example: 00000000-0000-4000-8000-000000000010
     *
     * @response 204
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 404 {"message":"Not Found"}
     */
    public function revokeShare(Request $request, Parcel $parcel): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.customer');
        abort_unless($parcel->sender_user_id === $request->user()->id, 404, 'Not Found');
        DB::transaction(function () use ($parcel, $request): void {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $parcel->update(['share_hash' => null]);
            $parcel->record('tracking_link_revoked', $request->user(), channel: 'customer');
        });

        return response()->json(null, 204);
    }

    /**
     * Liste les moyens de paiement autorisés pour le corridor du colis.
     * Réservé à l’expéditeur propriétaire. Les prestataires sont filtrés par pays et les capacités Wallet par corridor.
     * Aucun secret ni payload prestataire n’est exposé.
     *
     * @authenticated
     *
     * @urlParam parcel_uuid string required UUID public du colis. Example: 00000000-0000-4000-8000-000000000010
     *
     * @response 200 {"data":{"country":"BJ","currency":"XOF","methods":[{"code":"mobile_money","label":"Mobile Money","enabled":false,"providers":[]},{"code":"card","label":"Carte bancaire","enabled":false,"providers":[]},{"code":"wallet","label":"Wallet KORA","enabled":true,"availability":"points_only","providers":[],"requires":["wallet_amount"],"capabilities":{"cash":false,"points":true},"min_points_amount":100,"point_value_fcfa":1}]}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 404 {"message":"Not Found"}
     */
    public function methods(Request $request, Parcel $parcel, PaymentManager $manager): JsonResponse
    {
        $this->access->require($request->user(), 'parcels.customer');
        abort_unless($parcel->sender_user_id === $request->user()->id, 404, 'Not Found');
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

        return response()->json(['data' => ['country' => $country, 'currency' => app(GeneralSettings::class)->currency, 'methods' => $methods]]);
    }

    /**
     * Initie le paiement du dernier devis pesé.
     * Le montant envoyé doit correspondre exactement au devis serveur ; sinon 409. `idempotency_key` évite un double débit.
     * Un colis refusé ou bloqué par un incident ne peut jamais être payé.
     *
     * @authenticated
     *
     * @urlParam parcel_uuid string required UUID public du colis. Example: 00000000-0000-4000-8000-000000000010
     *
     * @response 201 {"data":{"uuid":"00000000-0000-4000-8000-000000000020","status":"confirmed","amount":1500,"currency":"XOF","method":"wallet","provider":"wallet","provider_reference":null,"provider_transaction_id":null,"confirmed_at":"2026-11-20T09:30:00+01:00","last_verified_at":null,"next_action":"show_parcel","polling_interval_seconds":10}}
     * @response 200 {"data":{"uuid":"00000000-0000-4000-8000-000000000020","status":"confirmed","amount":1500,"currency":"XOF","method":"wallet","provider":"wallet","provider_reference":null,"provider_transaction_id":null,"confirmed_at":"2026-11-20T09:30:00+01:00","last_verified_at":null,"next_action":"show_parcel","polling_interval_seconds":10}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 404 {"message":"Not Found"}
     * @response 409 {"message":"Le montant doit correspondre au dernier devis pesé ou un paiement est en cours."}
     * @response 422 {"message":"Le moyen de paiement cash n’est pas disponible depuis l’application.","errors":{"method":["Le moyen de paiement cash n’est pas disponible depuis l’application."]}}
     * @response 429 {"message":"Too Many Attempts."}
     */
    public function pay(ParcelPaymentRequest $request, Parcel $parcel, ParcelPayments $payments): PaymentResource
    {
        return new PaymentResource($payments->pay($parcel, $request->user(), $request->validated(), false));
    }

    /**
     * Consulte l’état d’une tentative de paiement du colis.
     * Réservé à l’expéditeur propriétaire. Retourne l’état de vérification, le montant et la prochaine action client.
     *
     * @authenticated
     *
     * @urlParam parcel_uuid string required UUID public du colis. Example: 00000000-0000-4000-8000-000000000010
     * @urlParam payment_uuid string required UUID public du paiement. Example: 00000000-0000-4000-8000-000000000020
     *
     * @response 200 {"data":{"uuid":"00000000-0000-4000-8000-000000000020","status":"pending","amount":1500,"currency":"XOF","method":"mobile_money","provider":"fedapay","provider_reference":"TEST-REF-001","confirmed_at":null,"last_verified_at":null,"next_action":"verify","polling_interval_seconds":10}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 404 {"message":"Not Found"}
     */
    public function payment(Request $request, Parcel $parcel, Payment $payment): PaymentResource
    {
        $this->assertPaymentOwner($request, $parcel, $payment);

        return new PaymentResource($payment);
    }

    /**
     * Vérifie la transaction auprès du prestataire et met à jour l’état du paiement.
     * Réservé à l’expéditeur propriétaire. Idempotent si le prestataire n’est pas supporté (retourne l’état courant),
     * sinon la vérification est bornée par le rate limit. Aucun payload prestataire n’est retourné.
     *
     * @authenticated
     *
     * @urlParam parcel_uuid string required UUID public du colis. Example: 00000000-0000-4000-8000-000000000010
     * @urlParam payment_uuid string required UUID public du paiement. Example: 00000000-0000-4000-8000-000000000020
     *
     * @response 200 {"data":{"uuid":"00000000-0000-4000-8000-000000000020","status":"confirmed","amount":1500,"currency":"XOF","method":"mobile_money","provider":"fedapay","provider_reference":"TEST-REF-001","provider_transaction_id":"TEST-TX-001","confirmed_at":"2026-11-20T09:35:00+01:00","last_verified_at":"2026-11-20T09:35:00+01:00","next_action":"show_parcel","polling_interval_seconds":10}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 404 {"message":"Not Found"}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     * @response 429 {"message":"Too Many Attempts."}
     */
    public function verify(ParcelPaymentRequest $request, Parcel $parcel, Payment $payment, ParcelPayments $payments, PaymentManager $manager): PaymentResource
    {
        $this->assertPaymentOwner($request, $parcel, $payment);

        return new PaymentResource($manager->supports($payment->provider)
            ? $payments->verify($payment, $request->validated('transaction_id'))
            : $payment);
    }

    /**
     * Récupère l’étiquette QR ou le reçu du colis.
     * `label` est accessible au titulaire et au destinataire autorisé, sans nom ni téléphone.
     * `receipt` et `return_receipt` sont réservés à l’expéditeur propriétaire. `handoff_proof` reste une opération OPS
     * et n’est jamais servi par cette API client (404).
     *
     * @authenticated
     *
     * @urlParam parcel_uuid string required UUID public du colis. Example: 00000000-0000-4000-8000-000000000010
     *
     * @response 200 {"data":{"kind":"label","width_mm":58,"reference":"KC-20261119-95262","qr_payload":"https://staging.koraexpress.app/api/v1/parcels/tracking/KC-20261119-95262","esc_pos_base64":"G1A="}}
     * @response 200 {"data":{"kind":"receipt","receipt_status":"provisional","reference":"KC-20261119-95262","currency":"XOF","charge":{"net":1271,"tax":229,"total":1500},"fiscal":{"status":"pending","certified":false},"width_mm":58,"fiscal_status":"pending"}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 404 {"message":"Not Found"}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     */
    public function documents(ParcelOperationRequest $request, Parcel $parcel, ParcelDocuments $documents): JsonResponse
    {
        $parcel = $this->access->customer($request->user())->findOrFail($parcel->id);
        $kind = $request->validated('kind') ?? 'label';
        abort_unless(in_array($kind, ['label', 'receipt', 'return_receipt'], true), 404, 'Unsupported document kind.');
        if ($kind !== 'label') {
            abort_unless($parcel->sender_user_id === $request->user()->id, 404, 'Not Found');
        }

        return response()->json(['data' => $documents->render($parcel, $kind)]);
    }

    /**
     * Signale un incident non bloquant sur un colis référencé.
     * Accessible au titulaire ou au destinataire vérifié. Retourne uniquement l’UUID de l’incident, jamais le code
     * de retrait ni le contenu du colis. L’incident est audité et notifié aux OPS.
     *
     * @authenticated
     *
     * @urlParam parcel_uuid string required UUID public du colis. Example: 00000000-0000-4000-8000-000000000010
     *
     * @bodyParam kind string required Nature du signalement. Example: damage
     * @bodyParam description string required Description factuelle, sans code de retrait. Example: Emballage endommagé
     * @bodyParam idempotency_key string UUID optionnel pour rejouer la déclaration sans créer un second incident. Example: 00000000-0000-4000-8000-000000000099
     *
     * @response 200 {"data":{"uuid":"00000000-0000-4000-8000-000000000030","blocking":false}}
     * @response 201 {"data":{"uuid":"00000000-0000-4000-8000-000000000030","blocking":false}}
     * @response 403 {"message":"Droits insuffisants pour ce périmètre Colis."}
     * @response 404 {"message":"Not Found"}
     * @response 409 {"message":"Opération incompatible avec l’état du colis ou du corridor."}
     * @response 422 {"message":"Les données de cette opération sont invalides."}
     * @response 429 {"message":"Too Many Attempts."}
     */
    public function incident(ParcelOperationRequest $request, Parcel $parcel, ParcelNotifications $notifications): JsonResponse
    {
        $parcel = $this->access->customer($request->user())->findOrFail($parcel->id);
        $data = $request->validated();
        unset($data['blocking']);
        $idempotencyKey = $data['idempotency_key'] ?? null;
        unset($data['idempotency_key']);

        return DB::transaction(function () use ($request, $parcel, $notifications, $data, $idempotencyKey): JsonResponse {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            abort_if($parcel->reference === null, 409, 'The parcel has no logistics reference.');

            if ($idempotencyKey !== null) {
                $existing = $parcel->incidents()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    abort_unless(
                        $existing->blocking === false
                        && $existing->kind === $data['kind']
                        && $existing->description === $data['description'],
                        409,
                        'This idempotency key was already used for another incident.',
                    );

                    return response()->json(['data' => ['uuid' => $existing->uuid, 'blocking' => false]], 200);
                }
            }

            $incident = $parcel->incidents()->create($data + [
                'blocking' => false,
                'opened_by_id' => $request->user()->id,
                'idempotency_key' => $idempotencyKey,
            ]);
            $parcel->record('incident_reported', $request->user(), details: [
                'incident_uuid' => $incident->uuid,
                'kind' => $incident->kind,
                'blocking' => false,
            ], channel: 'customer');
            $notifications->incident($parcel, $incident->uuid);

            return response()->json(['data' => ['uuid' => $incident->uuid, 'blocking' => false]], 201);
        });
    }

    private function assertPaymentOwner(Request $request, Parcel $parcel, Payment $payment): void
    {
        $this->access->require($request->user(), 'parcels.customer');
        abort_unless($parcel->sender_user_id === $request->user()->id
            && $parcel->charges()->whereKey($payment->parcel_charge_id)->exists(), 404);
    }
}
