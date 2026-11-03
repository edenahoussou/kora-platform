<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Actions\FindOrCreateWalletAction;
use App\Modules\Wallet\Actions\InitiateWalletTopUpAction;
use App\Modules\Wallet\Http\Requests\InitiateTopUpRequest;
use App\Modules\Wallet\Http\Resources\WalletTopUpResource;
use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Wallet
 */
class WalletTopUpController extends Controller
{
    /**
     * Initie une recharge Wallet via un provider de paiement existant.
     *
     * @param  InitiateTopUpRequest  $request  Montant, méthode et provider validés
     * @param  FindOrCreateWalletAction  $findOrCreateWallet  Provisionnement du Wallet
     * @param  InitiateWalletTopUpAction  $initiateTopUp  Création de la recharge et du paiement
     * @return JsonResponse Recharge créée (201) ou rejouée (200)
     */
    public function store(
        InitiateTopUpRequest $request,
        FindOrCreateWalletAction $findOrCreateWallet,
        InitiateWalletTopUpAction $initiateTopUp,
    ): JsonResponse {
        $wallet = $findOrCreateWallet->handle($request->user());
        $data = $request->topUpData();

        $result = $initiateTopUp->handle(
            wallet: $wallet,
            amount: $data['amount'],
            method: $data['method'],
            provider: $data['provider'],
            idempotencyKey: $data['idempotency_key'],
            phone: $data['phone'],
            mobileMoneyNetwork: $data['mobile_money_network'],
        );

        return (new WalletTopUpResource($result['topup']->loadMissing('payment')))
            ->response()
            ->setStatusCode($result['is_replay'] ? 200 : 201);
    }

    /**
     * Retourne le statut d'une recharge du voyageur connecté.
     *
     * @param  Request  $request  Requête authentifiée Sanctum
     * @param  string  $uuid  UUID de la recharge
     * @param  FindOrCreateWalletAction  $findOrCreateWallet  Provisionnement du Wallet
     * @return JsonResponse Recharge et statut du paiement provider
     */
    public function show(Request $request, string $uuid, FindOrCreateWalletAction $findOrCreateWallet): JsonResponse
    {
        $wallet = $findOrCreateWallet->handle($request->user());

        $topup = WalletTopUp::query()
            ->where('wallet_id', $wallet->getKey())
            ->where('uuid', $uuid)
            ->with('payment')
            ->firstOrFail();

        return (new WalletTopUpResource($topup))->response()->setStatusCode(200);
    }
}
