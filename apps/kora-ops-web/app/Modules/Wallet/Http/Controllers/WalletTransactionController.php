<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Actions\FindOrCreateWalletAction;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Http\Resources\WalletTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Wallet
 */
class WalletTransactionController extends Controller
{
    /**
     * Liste l'historique Wallet, filtrable par nature de solde.
     *
     * @param  Request  $request  Requête avec `balance_type` optionnel
     * @param  FindOrCreateWalletAction  $findOrCreateWallet  Provisionnement du Wallet
     * @return JsonResponse Écritures paginées par curseur
     *
     * @response 200 {"data":[{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","balance_type":"cash","direction":"credit","amount":5000,"reason":"topup","reference":{"type":"wallet_topup","uuid":"a4a5d8fd-8168-4f40-b6b5-437f97cf41f0"},"meta":null,"created_at":"2026-11-07T10:00:00+00:00"}],"links":{"next":null,"prev":null},"meta":{"per_page":20}}
     */
    public function index(Request $request, FindOrCreateWalletAction $findOrCreateWallet): JsonResponse
    {
        $wallet = $findOrCreateWallet->handle($request->user());
        $balanceType = $request->query('balance_type');

        $query = $wallet->transactions()->with('reference')->orderByDesc('id');

        if (is_string($balanceType) && in_array($balanceType, WalletBalanceType::values(), true)) {
            $query->where('balance_type', $balanceType);
        }

        return WalletTransactionResource::collection($query->cursorPaginate(20))->response();
    }
}
