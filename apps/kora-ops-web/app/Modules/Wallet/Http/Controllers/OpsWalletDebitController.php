<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Actions\RecordCounterSalePaymentAction;
use App\Modules\Wallet\Http\Requests\OpsWalletDebitRequest;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Wallet OPS
 *
 * Débit Wallet au guichet sans application, tracé agent/agence.
 */
class OpsWalletDebitController extends Controller
{
    /**
     * Débite le Wallet d'un voyageur pour une vente guichet.
     *
     * @param  OpsWalletDebitRequest  $request  Réservation, montants et clé d'idempotence
     * @param  Wallet  $wallet  Wallet lié par UUID
     * @param  RecordCounterSalePaymentAction  $recordCounterSalePayment  Parcours de vente guichet
     * @return JsonResponse Réservation payée et soldes restants
     *
     * @response 201 {"data":{"reservation_uuid":"9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e","payment_uuid":"a4a5d8fd-8168-4f40-b6b5-437f97cf41f0","wallet_uuid":"fba6df9a-8f10-4387-baf4-5140cbf64a6a","cash_balance":5000,"points_balance":200}}
     */
    public function __invoke(
        OpsWalletDebitRequest $request,
        Wallet $wallet,
        RecordCounterSalePaymentAction $recordCounterSalePayment,
    ): JsonResponse {
        Gate::authorize('debit', $wallet);

        $reservation = Reservation::query()
            ->where('uuid', $request->string('reservation_uuid')->value())
            ->firstOrFail();

        $result = $recordCounterSalePayment->execute($reservation, $request->user(), [
            'method' => 'wallet',
            'amount' => (int) $reservation->total_amount,
            'idempotency_key' => $request->string('idempotency_key')->value(),
            'wallet_uuid' => $wallet->uuid,
            'wallet_amount' => $request->integer('wallet_amount'),
            'points_amount' => $request->integer('points_amount'),
            'wallet_holder_phone' => $request->string('wallet_holder_phone')->value(),
            'wallet_holder_confirmed' => $request->boolean('wallet_holder_confirmed'),
            'payer_phone' => $request->string('wallet_holder_phone')->value(),
        ]);

        return response()->json([
            'data' => [
                'reservation_uuid' => $result['reservation']->uuid,
                'payment_uuid' => $result['payment']->uuid,
                'wallet_uuid' => $wallet->uuid,
                'cash_balance' => (int) $wallet->fresh()->cash_balance,
                'points_balance' => (int) $wallet->fresh()->points_balance,
            ],
        ], 201);
    }
}
