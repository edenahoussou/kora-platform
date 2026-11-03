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
     * Attach a referral code to the authenticated traveler Wallet.
     *
     * @authenticated
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
