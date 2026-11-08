<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Actions\RecordWalletAdjustmentAction;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Http\Requests\WalletAdjustmentRequest;
use App\Modules\Wallet\Http\Resources\WalletTransactionResource;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Wallet OPS
 *
 * Écriture corrective auditée sur un Wallet (crédit ou débit, motif obligatoire).
 */
class OpsWalletAdjustmentController extends Controller
{
    /**
     * Enregistre un ajustement Wallet audité.
     *
     * @param  WalletAdjustmentRequest  $request  Solde, sens, montant et motif
     * @param  Wallet  $wallet  Wallet corrigé
     * @param  RecordWalletAdjustmentAction  $recordAdjustment  Écriture corrective
     * @return JsonResponse Écriture créée
     *
     * @response 201 {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","balance_type":"points","direction":"credit","amount":150,"reason":"adjustment","reference":{"type":"wallet","uuid":"fba6df9a-8f10-4387-baf4-5140cbf64a6a"},"meta":{"reason":"Geste commercial validé"},"created_at":"2026-11-07T10:00:00+00:00"}}
     */
    public function __invoke(
        WalletAdjustmentRequest $request,
        Wallet $wallet,
        RecordWalletAdjustmentAction $recordAdjustment,
    ): JsonResponse {
        Gate::authorize('adjust', $wallet);

        $transaction = $recordAdjustment->handle(
            wallet: $wallet,
            type: WalletBalanceType::from($request->string('balance_type')->value()),
            direction: $request->string('direction')->value(),
            amount: $request->integer('amount'),
            reason: $request->string('reason')->value(),
            actor: $request->user(),
        );

        return (new WalletTransactionResource($transaction))->response()->setStatusCode(201);
    }
}
