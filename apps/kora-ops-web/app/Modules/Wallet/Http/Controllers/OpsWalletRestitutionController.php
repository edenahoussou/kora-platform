<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\Wallet\Actions\ApproveRestitutionAction;
use App\Modules\Wallet\Actions\MarkRestitutionPaidAction;
use App\Modules\Wallet\Actions\RejectRestitutionAction;
use App\Modules\Wallet\Actions\RequestRestitutionAction;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Http\Requests\IndexWalletRestitutionsRequest;
use App\Modules\Wallet\Http\Requests\MarkRestitutionPaidRequest;
use App\Modules\Wallet\Http\Requests\RejectRestitutionRequest;
use App\Modules\Wallet\Http\Requests\StoreRestitutionRequest;
use App\Modules\Wallet\Http\Resources\WalletRestitutionResource;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletRestitution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @group Wallet OPS
 *
 * Workflow de restitution exceptionnelle : demande, approbation, versement tracé.
 */
class OpsWalletRestitutionController extends Controller
{
    /**
     * Renders the OPS restitution queue for reviewers and the requests owned by counter agents.
     */
    public function page(IndexWalletRestitutionsRequest $request): Response
    {
        $actor = $request->user();
        $ability = Gate::allows('viewQueue', WalletRestitution::class) ? 'viewQueue' : 'viewOwn';
        Gate::authorize($ability, WalletRestitution::class);
        $canManage = Gate::allows('manage', WalletRestitution::class);
        $canRequest = Gate::allows('request', WalletRestitution::class);
        $status = $request->validated('status');

        $query = WalletRestitution::query()
            ->with(['wallet.user', 'requestedBy', 'approvedBy'])
            ->orderByDesc('id');

        if (! $canManage && $actor !== null) {
            $query->where('requested_by_user_id', $actor->getKey());
        }

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $source = $request->validated('source');
        if (is_string($source) && $source !== '') {
            $query->where('uuid', $source);
        }

        $basePath = match (true) {
            $request->routeIs('kora-point.wallet-restitutions.index') => '/kora-point/wallet-restitutions',
            $request->routeIs('kora-partner.wallet-restitutions.index') => '/kora-partner/wallet-restitutions',
            default => '/administration/wallet-restitutions',
        };

        return Inertia::render('administration/wallet-restitutions/index', [
            'restitutions' => $query->paginate(25)->withQueryString()->through(
                fn (WalletRestitution $restitution): array => (new WalletRestitutionResource($restitution))->resolve($request),
            ),
            'filters' => ['status' => is_string($status) && $status !== '' ? $status : 'all'],
            'basePath' => $basePath,
            'lookupPath' => str_replace('wallet-restitutions', 'wallet-lookup', $basePath),
            'permissions' => [
                'canRequest' => $canRequest,
                'canManage' => $canManage,
            ],
            'statuses' => [
                'requested' => 'À valider',
                'approved' => 'Approuvée',
                'rejected' => 'Rejetée',
                'paid' => 'Versée',
            ],
        ]);
    }

    /**
     * Liste les restitutions, filtrables par statut.
     *
     * @param  Request  $request  Requête avec `status` optionnel
     * @return AnonymousResourceCollection Restitutions paginées
     *
     * @response 200 {"data":[{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","wallet_uuid":"fba6df9a-8f10-4387-baf4-5140cbf64a6a","holder":{"name":"Aïcha Hounkpè","phone":"+22997000000"},"amount":4000,"status":"requested","note":"Erreur de recharge"}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', WalletRestitution::class);

        $query = WalletRestitution::query()
            ->with(['wallet.user', 'requestedBy', 'approvedBy'])
            ->orderByDesc('id');

        $status = $request->query('status');
        if (is_string($status) && in_array($status, RestitutionStatus::values(), true)) {
            $query->where('status', $status);
        }

        return WalletRestitutionResource::collection($query->paginate(25));
    }

    /**
     * Ouvre une demande de restitution.
     *
     * @param  StoreRestitutionRequest  $request  Wallet, montant et note
     * @param  RequestRestitutionAction  $requestRestitution  Contrôle du disponible
     * @return JsonResponse Restitution demandée
     *
     * @response 201 {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","wallet_uuid":"fba6df9a-8f10-4387-baf4-5140cbf64a6a","holder":{"name":"Aïcha Hounkpè","phone":"+22997000000"},"amount":4000,"status":"requested","note":"Erreur de recharge"}}
     */
    public function store(
        StoreRestitutionRequest $request,
        RequestRestitutionAction $requestRestitution,
        PassengerIdentityResolver $identityResolver,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('request', WalletRestitution::class);

        $wallet = Wallet::query()
            ->with('user')
            ->where('uuid', $request->string('wallet_uuid')->value())
            ->firstOrFail();
        $holderPhone = $identityResolver->normalizePhone($request->string('holder_phone')->value());
        $walletPhone = $identityResolver->normalizePhone($wallet->user->phone);

        if ($holderPhone === null || $walletPhone === null || $holderPhone !== $walletPhone) {
            throw ValidationException::withMessages([
                'holder_phone' => 'Le numéro ne correspond pas au titulaire de ce Wallet.',
            ]);
        }

        $restitution = $requestRestitution->handle(
            $wallet,
            $request->integer('amount'),
            $request->user(),
            $request->string('note')->value() ?: null,
        );

        return $this->mutationResponse(
            $request,
            (new WalletRestitutionResource($restitution->load(['wallet.user', 'requestedBy'])))->response()->setStatusCode(201),
            'La demande de restitution a été enregistrée.',
        );
    }

    /**
     * Approuve une restitution en attente.
     *
     * @param  Request  $request  Requête de l'approbateur habilité
     * @param  WalletRestitution  $restitution  Restitution liée par UUID
     * @param  ApproveRestitutionAction  $approveRestitution  Passage en « approuvée »
     * @return JsonResponse Restitution approuvée
     *
     * @response 200 {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","amount":4000,"status":"approved","approved_by":"Responsable Finance","approved_at":"2026-11-07T10:00:00+00:00"}}
     */
    public function approve(
        Request $request,
        WalletRestitution $restitution,
        ApproveRestitutionAction $approveRestitution,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('approve', $restitution);

        $restitution = $approveRestitution->handle($restitution, $request->user());

        return $this->mutationResponse(
            $request,
            (new WalletRestitutionResource($restitution->load(['wallet.user', 'approvedBy'])))->response(),
            'La restitution a été approuvée.',
        );
    }

    /**
     * Rejette une restitution avec un motif.
     *
     * @param  RejectRestitutionRequest  $request  Motif du rejet
     * @param  WalletRestitution  $restitution  Restitution liée par UUID
     * @param  RejectRestitutionAction  $rejectRestitution  Passage en « rejetée »
     * @return JsonResponse Restitution rejetée
     *
     * @response 200 {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","amount":4000,"status":"rejected","rejection_reason":"Vérification nécessaire"}}
     */
    public function reject(
        RejectRestitutionRequest $request,
        WalletRestitution $restitution,
        RejectRestitutionAction $rejectRestitution,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('reject', $restitution);

        $restitution = $rejectRestitution->handle($restitution, $request->user(), $request->string('reason')->value());

        return $this->mutationResponse(
            $request,
            (new WalletRestitutionResource($restitution->load(['wallet.user', 'approvedBy'])))->response(),
            'La demande a été rejetée.',
        );
    }

    /**
     * Marque une restitution versée et débite le Wallet.
     *
     * @param  MarkRestitutionPaidRequest  $request  Référence et canal du versement
     * @param  WalletRestitution  $restitution  Restitution liée par UUID
     * @param  MarkRestitutionPaidAction  $markRestitutionPaid  Débit tracé
     * @return JsonResponse Restitution versée
     *
     * @response 200 {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","amount":4000,"status":"paid","payout_reference":"MM-2026-0001","payout_channel":"mobile_money","paid_at":"2026-11-07T10:00:00+00:00"}}
     */
    public function markPaid(
        MarkRestitutionPaidRequest $request,
        WalletRestitution $restitution,
        MarkRestitutionPaidAction $markRestitutionPaid,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('markPaid', $restitution);

        $restitution = $markRestitutionPaid->handle(
            $restitution,
            $request->user(),
            $request->string('payout_reference')->value(),
            $request->string('payout_channel')->value() ?: null,
        );

        return $this->mutationResponse(
            $request,
            (new WalletRestitutionResource($restitution->load(['wallet.user', 'approvedBy'])))->response(),
            'Le versement est tracé et le Wallet a été débité.',
        );
    }

    /**
     * Returns JSON to API clients and redirects Inertia form submissions back to the queue.
     */
    private function mutationResponse(Request $request, JsonResponse $response, string $message): JsonResponse|RedirectResponse
    {
        if ($request->headers->has('X-Inertia')) {
            return back()->with('success', $message);
        }

        return $response;
    }
}
