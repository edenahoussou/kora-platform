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
use Illuminate\Support\Facades\Gate;

/**
 * @group Wallet
 */
class WalletTopUpController extends Controller
{
    /**
     * Initie une recharge Wallet via un provider de paiement existant.
     *
     * @authenticated
     *
     * @param  InitiateTopUpRequest  $request  Montant, méthode et provider validés
     * @param  FindOrCreateWalletAction  $findOrCreateWallet  Provisionnement du Wallet
     * @param  InitiateWalletTopUpAction  $initiateTopUp  Création de la recharge et du paiement
     * @return JsonResponse Recharge créée (201) ou rejouée (200)
     *
     * @response 201 {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","amount":5000,"status":"pending","payment":{"uuid":"a4a5d8fd-8168-4f40-b6b5-437f97cf41f0","status":"pending","provider":"kkiapay","provider_reference":null,"next_action":"open_widget","widget":{"key":"public-sandbox-key","sandbox":true,"amount":5000,"partnerId":"a4a5d8fd-8168-4f40-b6b5-437f97cf41f0","paymentmethod":["momo"],"countries":["BJ"]},"checkout_url":null,"polling_interval_seconds":10,"failure":null},"created_at":"2026-11-07T10:00:00+00:00"}}
     * @response 200 scenario="Recharge idempotente déjà créée" {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","amount":5000,"status":"pending","payment":{"uuid":"a4a5d8fd-8168-4f40-b6b5-437f97cf41f0","status":"pending","provider":"fedapay","provider_reference":null,"next_action":"verify","widget":null,"checkout_url":null,"polling_interval_seconds":10,"failure":null},"created_at":"2026-11-07T10:00:00+00:00"}}
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
     * @authenticated
     *
     * @param  Request  $request  Requête authentifiée Sanctum
     * @param  string  $uuid  UUID de la recharge
     * @return JsonResponse Recharge et statut du paiement provider
     *
     * @response 200 {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","amount":5000,"status":"pending","payment":{"uuid":"a4a5d8fd-8168-4f40-b6b5-437f97cf41f0","status":"pending","provider":"fedapay","provider_reference":null,"next_action":"verify","widget":null,"checkout_url":null,"polling_interval_seconds":10,"failure":null},"created_at":"2026-11-07T10:00:00+00:00"}}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $topup = WalletTopUp::query()
            ->whereHas('wallet', fn ($wallet) => $wallet->where('user_id', $request->user()->getKey()))
            ->where('uuid', $uuid)->with('payment')->firstOrFail();
        Gate::authorize('view', $topup->payment);

        return (new WalletTopUpResource($topup))->response();
    }
}
