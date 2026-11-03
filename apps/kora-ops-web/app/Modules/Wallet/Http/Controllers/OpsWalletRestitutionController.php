<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Actions\ApproveRestitutionAction;
use App\Modules\Wallet\Actions\MarkRestitutionPaidAction;
use App\Modules\Wallet\Actions\RejectRestitutionAction;
use App\Modules\Wallet\Actions\RequestRestitutionAction;
use App\Modules\Wallet\Data\RestitutionStatus;
use App\Modules\Wallet\Http\Requests\MarkRestitutionPaidRequest;
use App\Modules\Wallet\Http\Requests\RejectRestitutionRequest;
use App\Modules\Wallet\Http\Requests\StoreRestitutionRequest;
use App\Modules\Wallet\Http\Resources\WalletRestitutionResource;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletRestitution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Wallet OPS
 *
 * Workflow de restitution exceptionnelle : demande, approbation, versement tracé.
 */
class OpsWalletRestitutionController extends Controller
{
    /**
     * Liste les restitutions, filtrables par statut.
     *
     * @param  Request  $request  Requête avec `status` optionnel
     * @return AnonymousResourceCollection Restitutions paginées
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('wallet.manage-restitutions'), 403);

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
     */
    public function store(StoreRestitutionRequest $request, RequestRestitutionAction $requestRestitution): JsonResponse
    {
        abort_unless($request->user()?->can('wallet.debit-counter'), 403);

        $wallet = Wallet::query()->where('uuid', $request->string('wallet_uuid')->value())->firstOrFail();

        $restitution = $requestRestitution->handle(
            $wallet,
            $request->integer('amount'),
            $request->user(),
            $request->string('note')->value() ?: null,
        );

        return (new WalletRestitutionResource($restitution->load(['wallet.user', 'requestedBy'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Approuve une restitution en attente.
     *
     * @param  Request  $request  Requête de l'approbateur habilité
     * @param  WalletRestitution  $restitution  Restitution liée par UUID
     * @param  ApproveRestitutionAction  $approveRestitution  Passage en « approuvée »
     * @return JsonResponse Restitution approuvée
     */
    public function approve(
        Request $request,
        WalletRestitution $restitution,
        ApproveRestitutionAction $approveRestitution,
    ): JsonResponse {
        abort_unless($request->user()?->can('wallet.manage-restitutions'), 403);

        $restitution = $approveRestitution->handle($restitution, $request->user());

        return (new WalletRestitutionResource($restitution->load(['wallet.user', 'approvedBy'])))->response();
    }

    /**
     * Rejette une restitution avec un motif.
     *
     * @param  RejectRestitutionRequest  $request  Motif du rejet
     * @param  WalletRestitution  $restitution  Restitution liée par UUID
     * @param  RejectRestitutionAction  $rejectRestitution  Passage en « rejetée »
     * @return JsonResponse Restitution rejetée
     */
    public function reject(
        RejectRestitutionRequest $request,
        WalletRestitution $restitution,
        RejectRestitutionAction $rejectRestitution,
    ): JsonResponse {
        abort_unless($request->user()?->can('wallet.manage-restitutions'), 403);

        $restitution = $rejectRestitution->handle($restitution, $request->user(), $request->string('reason')->value());

        return (new WalletRestitutionResource($restitution->load(['wallet.user', 'approvedBy'])))->response();
    }

    /**
     * Marque une restitution versée et débite le Wallet.
     *
     * @param  MarkRestitutionPaidRequest  $request  Référence et canal du versement
     * @param  WalletRestitution  $restitution  Restitution liée par UUID
     * @param  MarkRestitutionPaidAction  $markRestitutionPaid  Débit tracé
     * @return JsonResponse Restitution versée
     */
    public function markPaid(
        MarkRestitutionPaidRequest $request,
        WalletRestitution $restitution,
        MarkRestitutionPaidAction $markRestitutionPaid,
    ): JsonResponse {
        abort_unless($request->user()?->can('wallet.manage-restitutions'), 403);

        $restitution = $markRestitutionPaid->handle(
            $restitution,
            $request->user(),
            $request->string('payout_reference')->value(),
            $request->string('payout_channel')->value() ?: null,
        );

        return (new WalletRestitutionResource($restitution->load(['wallet.user', 'approvedBy'])))->response();
    }
}
