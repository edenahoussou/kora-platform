<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Actions\FindOrCreateWalletAction;
use App\Modules\Wallet\Http\Resources\WalletResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Wallet
 *
 * Soldes, paramètres UX et code de parrainage du Wallet du voyageur connecté.
 */
class WalletController extends Controller
{
    /**
     * Retourne le Wallet du voyageur connecté (créé à la volée si absent).
     *
     * @authenticated
     *
     * @param  Request  $request  Requête authentifiée Sanctum
     * @param  FindOrCreateWalletAction  $findOrCreateWallet  Provisionnement du Wallet
     * @return JsonResponse Soldes, échéance de points, code parrain et paramètres UX
     *
     * @response 200 {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","cash_balance":0,"points_balance":0,"points_expires_at":null,"referral_code":"KORA-ABC123","referred_by":false,"holder":{"name":"Aïcha Hounkpè","phone":"+22997000000"},"settings":{"wallet_cash_enabled":false,"wallet_points_enabled":true,"point_value_fcfa":1,"points_min_spend":100},"created_at":"2026-11-07T10:00:00+00:00"}}
     */
    public function show(Request $request, FindOrCreateWalletAction $findOrCreateWallet): JsonResponse
    {
        $wallet = $findOrCreateWallet->handle($request->user());

        return (new WalletResource($wallet->loadMissing('user')))->response()->setStatusCode(200);
    }
}
