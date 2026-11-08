<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Http\Requests\OpsWalletLookupRequest;
use App\Modules\Wallet\Http\Resources\WalletResource;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Wallet OPS
 *
 * Recherche du Wallet d'un voyageur par téléphone avant un débit au guichet.
 */
class OpsWalletLookupController extends Controller
{
    /**
     * Recherche un Wallet par téléphone avant un débit au guichet.
     *
     * @param  OpsWalletLookupRequest  $request  Requête validée avec `phone`
     * @param  PassengerIdentityResolver  $identityResolver  Normalisation du téléphone
     * @param  WalletLedgerService  $ledger  Calcul du disponible
     * @return JsonResponse Titulaire, soldes et disponible, ou `data: null`
     *
     * @response 200 scenario="Wallet trouvé" {"data":{"uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","holder":{"name":"Aïcha Hounkpè","phone":"+22997000000"},"cash_balance":5000,"points_balance":200,"available":{"cash":5000,"points":200}}}
     * @response 200 scenario="Aucun Wallet pour ce téléphone" {"data":null}
     */
    public function __invoke(
        OpsWalletLookupRequest $request,
        PassengerIdentityResolver $identityResolver,
        WalletLedgerService $ledger,
    ): JsonResponse {
        Gate::authorize('lookup', Wallet::class);
        $normalized = $identityResolver->normalizePhone($request->string('phone')->value());
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
