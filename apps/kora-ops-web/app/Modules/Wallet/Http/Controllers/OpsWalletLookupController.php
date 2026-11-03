<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Http\Resources\WalletResource;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Wallet OPS
 *
 * Recherche du Wallet d'un voyageur par téléphone avant un débit au guichet.
 */
class OpsWalletLookupController extends Controller
{
    public function __invoke(
        Request $request,
        PassengerIdentityResolver $identityResolver,
        WalletLedgerService $ledger,
    ): JsonResponse {
        abort_unless($request->user()?->can('wallet.debit-counter'), 403);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $normalized = $identityResolver->normalizePhone($validated['phone']);
        abort_if($normalized === null, 422, 'Numéro de téléphone invalide.');

        $wallet = Wallet::query()
            ->whereHas('user', fn ($query) => $query->where('phone', $normalized))
            ->with('user')
            ->first();

        if ($wallet === null) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                ...(new WalletResource($wallet))->toArray($request),
                'available' => [
                    'cash' => $ledger->availableBalance($wallet, WalletBalanceType::Cash),
                    'points' => $ledger->availableBalance($wallet, WalletBalanceType::Points),
                ],
            ],
        ]);
    }
}
