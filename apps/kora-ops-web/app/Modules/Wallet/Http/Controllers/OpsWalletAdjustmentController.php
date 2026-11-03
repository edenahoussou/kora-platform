<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Actions\RecordWalletAdjustmentAction;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Http\Requests\WalletAdjustmentRequest;
use App\Modules\Wallet\Http\Resources\WalletTransactionResource;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Http\JsonResponse;

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
     */
    public function __invoke(
        WalletAdjustmentRequest $request,
        Wallet $wallet,
        RecordWalletAdjustmentAction $recordAdjustment,
    ): JsonResponse {
        abort_unless($request->user()?->can('wallet.adjust'), 403);

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
