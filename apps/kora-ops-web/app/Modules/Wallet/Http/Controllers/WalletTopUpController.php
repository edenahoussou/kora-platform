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
     * Initiate a Wallet top-up through an existing payment provider.
     *
     * @authenticated
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
     * Show a Wallet top-up status.
     *
     * @authenticated
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
