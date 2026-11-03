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
     * List Wallet transactions (cursor paginated).
     *
     * @authenticated
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
