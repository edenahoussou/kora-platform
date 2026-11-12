<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Actions\FindOrCreateWalletAction;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletDirection;
use App\Modules\Wallet\Http\Resources\WalletTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @group Wallet
 */
class WalletTransactionController extends Controller
{
    /**
     * Liste l'historique Wallet, filtrable par nature de solde et sens.
     *
     * @authenticated
     *
     * @queryParam balance_type string Solde Wallet (cash ou points). Example: cash
     * @queryParam direction string Sens de l’écriture (credit ou debit). Example: credit
     * @queryParam cursor string Curseur fourni par la réponse précédente. No-example
     *
     * @param  Request  $request  Requête avec `balance_type` optionnel
     * @param  FindOrCreateWalletAction  $findOrCreateWallet  Provisionnement du Wallet
     * @return JsonResponse Écritures paginées par curseur
     *
     * @response 200 {"data":[{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","balance_type":"cash","direction":"credit","amount":5000,"reason":"topup","reference":{"type":"wallet_topup","uuid":"a4a5d8fd-8168-4f40-b6b5-437f97cf41f0"},"meta":null,"created_at":"2026-11-07T10:00:00+00:00"}],"links":{"next":null,"prev":null},"meta":{"per_page":20}}
     */
    public function index(Request $request, FindOrCreateWalletAction $findOrCreateWallet): JsonResponse
    {
        // Query parameters
        $filters = Validator::make($request->query(), [
            'balance_type' => ['sometimes', 'string', Rule::in(WalletBalanceType::values())],
            'direction' => ['sometimes', 'string', Rule::in(WalletDirection::values())],
        ])->validate();
        $wallet = $findOrCreateWallet->handle($request->user());
        $query = $wallet->transactions()->with('reference')->orderByDesc('id');

        foreach ($filters as $column => $value) {
            $query->where($column, $value);
        }

        return WalletTransactionResource::collection($query->cursorPaginate(20)->appends($filters))->response();
    }
}
