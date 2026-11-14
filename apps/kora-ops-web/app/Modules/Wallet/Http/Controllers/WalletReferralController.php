<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wallet\Actions\AttachReferralCodeAction;
use App\Modules\Wallet\Actions\FindOrCreateWalletAction;
use App\Modules\Wallet\Http\Requests\AttachReferralRequest;
use App\Modules\Wallet\Http\Resources\WalletResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Wallet
 */
class WalletReferralController extends Controller
{
    /**
     * Rattache un code parrain au Wallet du voyageur connecté.
     *
     * @authenticated
     *
     * @param  AttachReferralRequest  $request  Code parrain validé
     * @param  FindOrCreateWalletAction  $findOrCreateWallet  Provisionnement du Wallet
     * @param  AttachReferralCodeAction  $attachReferralCode  Contrôles et rattachement
     * @return JsonResponse Wallet mis à jour
     *
     * @response 200 {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","cash_balance":0,"points_balance":0,"referral_code":"KORA-ABC123","referred_by":true}}
     * @response 422 {"message":"Le code de parrainage doit être saisi avant la première utilisation.","errors":{"referral_code":["Le code de parrainage doit être saisi avant la première utilisation."]}}
     */
    public function store(
        AttachReferralRequest $request,
        FindOrCreateWalletAction $findOrCreateWallet,
        AttachReferralCodeAction $attachReferralCode,
    ): JsonResponse {
        $wallet = $findOrCreateWallet->handle($request->user());
        $wallet = $attachReferralCode->handle($wallet, $request->string('referral_code')->value());

        return (new WalletResource($wallet->loadMissing('user')))->response()->setStatusCode(200);
    }
}
