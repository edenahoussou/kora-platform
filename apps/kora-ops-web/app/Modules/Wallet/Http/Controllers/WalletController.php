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
     * Display the authenticated traveler Wallet.
     *
     * @authenticated
     */
    public function show(Request $request, FindOrCreateWalletAction $findOrCreateWallet): JsonResponse
    {
        $wallet = $findOrCreateWallet->handle($request->user());

        return (new WalletResource($wallet->loadMissing('user')))->response()->setStatusCode(200);
    }
}
